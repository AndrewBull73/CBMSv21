<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$findings = is_array($findings ?? null) ? $findings : [];
$catalogInstalled = (bool) ($catalogInstalled ?? false);
$managementInstalled = (bool) ($managementInstalled ?? false);
$errorCount = 0;
$warningCount = 0;
foreach ($findings as $finding) {
    $severity = strtolower((string) ($finding['Severity'] ?? ''));
    if ($severity === 'error') {
        $errorCount++;
    } elseif ($severity === 'warning') {
        $warningCount++;
    }
}

$screenHeader = [
    'title' => 'Training Validation',
    'icon' => 'bi-check2-square',
];

$severityBadge = static function (string $severity): string {
    return match (strtolower($severity)) {
        'error' => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong>Training catalogue validation</strong>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Findings</div>
              <div class="fs-4 fw-semibold"><?= count($findings) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Errors</div>
              <div class="fs-4 fw-semibold text-danger"><?= $errorCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Warnings</div>
              <div class="fs-4 fw-semibold text-warning"><?= $warningCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Ready</div>
              <div class="fs-4 fw-semibold <?= $findings === [] ? 'text-success' : '' ?>"><?= $findings === [] ? 'Yes' : 'No' ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="training-validation-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Training Validation Runbook</div>
        <div class="mb-2">Use this screen before publishing or running structured training to find training steps that point to missing routes, unsupported completion modes, missing target element IDs, or sample key mismatches.</div>
        <div class="small text-muted mb-2">Errors usually stop a scenario from running. Warnings identify items that may still work but should be checked before a learner uses the flow.</div>
        <div class="small">Fix the scenario or step in Training Catalogue, then rerun validation.</div>
      </div>

      <?php if (!$catalogInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          Run <code>create_training_scenario_catalog.sql</code> before validating the training catalogue.
        </div>
      <?php elseif (!$managementInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          Run <code>create_training_management_features.sql</code> to enable the full training management feature set.
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Validation Findings</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Severity</th>
                  <th>Scenario</th>
                  <th class="text-end">Step</th>
                  <th>Issue</th>
                  <th>Detail</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($findings === []): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No validation findings were detected.</td></tr>
                <?php else: ?>
                  <?php foreach ($findings as $finding): ?>
                    <?php
                      $scenarioCode = (string) ($finding['ScenarioCode'] ?? '');
                      $stepNo = (int) ($finding['StepNo'] ?? 0);
                    ?>
                    <tr>
                      <td><span class="badge <?= $severityBadge((string) ($finding['Severity'] ?? '')) ?>"><?= h(ucfirst((string) ($finding['Severity'] ?? 'warning'))) ?></span></td>
                      <td><?= h($scenarioCode) ?></td>
                      <td class="text-end"><?= $stepNo > 0 ? $stepNo : '' ?></td>
                      <td><?= h((string) ($finding['Message'] ?? '')) ?></td>
                      <td><code><?= h((string) ($finding['Detail'] ?? '')) ?></code></td>
                      <td class="text-end text-nowrap">
                        <?php if ($scenarioCode !== '' && $stepNo > 0): ?>
                          <a class="btn btn-sm btn-outline-primary" href="index.php?route=training-admin/step-form&amp;scenario_code=<?= urlencode($scenarioCode) ?>&amp;step_no=<?= $stepNo ?>">
                            <i class="bi bi-pencil-square me-1"></i>Step
                          </a>
                        <?php elseif ($scenarioCode !== ''): ?>
                          <a class="btn btn-sm btn-outline-primary" href="index.php?route=training-admin/scenario-form&amp;scenario_code=<?= urlencode($scenarioCode) ?>">
                            <i class="bi bi-pencil-square me-1"></i>Scenario
                          </a>
                        <?php else: ?>
                          <span class="text-muted small">Review setup</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3 d-flex flex-wrap gap-2">
            <a href="index.php?route=training-admin/operations" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Training Operations
            </a>
            <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-journal-text me-1"></i>Training Catalogue
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
