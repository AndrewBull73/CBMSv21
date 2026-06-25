<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$screenHeader = [
    'title' => 'Strategic Budget Framework Guide',
    'icon' => 'bi-map',
];

$steps = [
    [
        'title' => 'Confirm the base setup',
        'purpose' => 'Make sure the shared budget foundation is ready before strategic planning starts.',
        'actions' => [
            'Confirm the fiscal year, budget version, currency, fiscal periods, and active DataObject scope.',
            'Confirm that required CBMS segments and segment values are loaded.',
            'Run Configuration Readiness and resolve critical issues before continuing.',
        ],
        'outputs' => 'A stable fiscal context and source data foundation.',
    ],
    [
        'title' => 'Map the strategic source dimensions',
        'purpose' => 'Tell the Strategy module which CBMS segment drives each strategic dimension.',
        'actions' => [
            'Map sectors, programs, sub-programs, projects, funding types, funding sources, and economic items where they are source-backed.',
            'Confirm mapped segments have segment values loaded.',
            'Mark dimensions as not mapped where they will be maintained directly in the Strategy module.',
        ],
        'outputs' => 'Clear source mapping for imported and maintained strategic records.',
    ],
    [
        'title' => 'Import and clean the core dimensions',
        'purpose' => 'Bring source-backed master data into the Strategy module and make it usable for planning.',
        'actions' => [
            'Import or maintain sectors, programs, sub-programs, projects, funding types, funding sources, and economic items.',
            'Review missing names, inactive values, duplicates, and orphaned records.',
            'Archive or correct records that should not be used in the current planning cycle.',
        ],
        'outputs' => 'Clean strategic master records for the active fiscal year.',
    ],
    [
        'title' => 'Build the planning framework',
        'purpose' => 'Create the policy and performance structure that explains why funding is needed.',
        'actions' => [
            'Create strategic pillars and goals.',
            'Create objectives and link them to the relevant programs, sectors, or goals.',
            'Define indicators and targets so performance can be reviewed alongside funding.',
        ],
        'outputs' => 'A strategic results framework that connects priorities, objectives, and measures.',
    ],
    [
        'title' => 'Prepare delivery and initial costing',
        'purpose' => 'Translate the framework into outputs, activities, projects, and early cost estimates.',
        'actions' => [
            'Create outputs and activities under the appropriate programs or projects.',
            'Enter initial activity budget lines where planning teams need a first cost view.',
            'Classify budget lines by sector, program, project, funding source, funding type, and economic item.',
        ],
        'outputs' => 'A draft strategic plan with enough costing detail to support funding decisions.',
    ],
    [
        'title' => 'Prepare and lodge funding requests',
        'purpose' => 'Capture funding demand before final ceilings are set.',
        'actions' => [
            'Create funding lodgement headers for each package of requested funding.',
            'Add funding items with requested amounts, classifications, justification, and supporting attachments.',
            'Submit complete lodgements for review.',
        ],
        'outputs' => 'A formal record of funding demand for review and approval.',
    ],
    [
        'title' => 'Review and approve funding submissions',
        'purpose' => 'Assess requests and decide what can be funded.',
        'actions' => [
            'Review each funding item and record recommendations, reductions, conditions, or rejection reasons.',
            'Approve, partially approve, reject, or fund submissions according to workflow rules.',
            'Publish approved funding decisions when they are ready to affect fiscal controls.',
        ],
        'outputs' => 'Approved funding decisions that can inform final resource controls.',
    ],
    [
        'title' => 'Set final resource envelopes and ceilings',
        'purpose' => 'Convert approved funding decisions into the fiscal control position.',
        'actions' => [
            'Use indicative ceilings early only where planners need a temporary planning control.',
            'After funding approvals, update or publish final sector ceilings.',
            'Confirm the resource envelope and sector ceilings align with approved funding decisions.',
        ],
        'outputs' => 'Final sector ceilings and resource envelope settings for the planning version.',
    ],
    [
        'title' => 'Check plan against ceilings',
        'purpose' => 'Confirm that planned activity costs fit within the fiscal control position.',
        'actions' => [
            'Review Ceiling vs Strategic Plan.',
            'Investigate over-ceiling sectors, missing classifications, or budget lines not included in totals.',
            'Adjust activities, funding decisions, or ceilings only through the correct workflow path.',
        ],
        'outputs' => 'A reconciled plan that is ready for readiness review.',
    ],
    [
        'title' => 'Run readiness checks and review reports',
        'purpose' => 'Validate that the framework is complete enough for submission or approval.',
        'actions' => [
            'Run Configuration Readiness and Submission Readiness.',
            'Review Strategic Summary, sector, program, project, MTFF, and performance reports.',
            'Resolve critical checks before final sign-off.',
        ],
        'outputs' => 'A reviewed Strategic Budget Framework ready for formal workflow action.',
    ],
    [
        'title' => 'Finalize the framework',
        'purpose' => 'Complete governance actions and preserve the audit trail.',
        'actions' => [
            'Move the version through the required approval or lock workflow.',
            'Export or publish reports required for submission packs.',
            'Keep assumptions, mappings, lodgements, approvals, and ceiling changes traceable.',
        ],
        'outputs' => 'An approved strategic budget version with supporting evidence.',
    ],
];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
          <i class="bi bi-printer me-1"></i>Print
        </button>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this guide as the high-level preparation sequence for the Strategic Budget Framework. Funding Lodgements should normally be completed and reviewed before final ceilings are set, because lodgements capture demand and ceilings represent the approved fiscal control position.
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Sequence principle</div>
              <div class="fw-semibold">Demand before final control</div>
              <div class="small text-muted mt-2">Use lodgements and approvals to inform final ceilings.</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Early planning option</div>
              <div class="fw-semibold">Indicative ceilings</div>
              <div class="small text-muted mt-2">Use temporary ceilings only when planners need a starting constraint.</div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Final check</div>
              <div class="fw-semibold">Readiness and reports</div>
              <div class="small text-muted mt-2">Resolve critical checks before workflow approval or publication.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Preparation Process</h5>
        </div>
        <div class="card-body">
          <div class="list-group list-group-flush">
            <?php foreach ($steps as $index => $step): ?>
              <div class="list-group-item px-0 py-3">
                <div class="d-flex gap-3">
                  <div>
                    <span class="badge text-bg-primary rounded-pill"><?= (int) ($index + 1) ?></span>
                  </div>
                  <div class="flex-grow-1">
                    <h6 class="mb-1"><?= h((string) $step['title']) ?></h6>
                    <div class="small text-muted mb-2"><?= h((string) $step['purpose']) ?></div>
                    <ul class="mb-2">
                      <?php foreach ($step['actions'] as $action): ?>
                        <li><?= h((string) $action) ?></li>
                      <?php endforeach; ?>
                    </ul>
                    <div class="small"><strong>Output:</strong> <?= h((string) $step['outputs']) ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Recommended Order at a Glance</h5>
        </div>
        <div class="card-body">
          <ol class="mb-0">
            <li>Base setup and strategic segment mapping.</li>
            <li>Import and clean dimensions.</li>
            <li>Build planning, performance, delivery, and initial costing structures.</li>
            <li>Prepare Funding Lodgements and submit them for review.</li>
            <li>Review, approve, and publish approved funding decisions.</li>
            <li>Set final resource envelopes and sector ceilings.</li>
            <li>Check ceiling vs plan, run readiness, review reports, and finalize.</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</div>
