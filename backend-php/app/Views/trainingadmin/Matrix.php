<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$paths = is_array($paths ?? null) ? $paths : [];
$roles = is_array($roles ?? null) ? $roles : [];
$statusValues = is_array($statusValues ?? null) ? $statusValues : [];
$scenarios = is_array($scenarios ?? null) ? $scenarios : [];
$summary = is_array($summary ?? null) ? $summary : [];
$filters = is_array($filters ?? null) ? $filters : [];
$pathOptions = is_array($pathOptions ?? null) ? $pathOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$documentPath = trim((string) ($documentPath ?? ''));
$documentModified = (int) ($documentModified ?? 0);
$documentAvailable = (bool) ($documentAvailable ?? false);
$selectedPath = trim((string) ($filters['path'] ?? ''));
$selectedStatus = strtoupper(trim((string) ($filters['status'] ?? '')));
$selectedSearch = trim((string) ($filters['q'] ?? ''));
$contextSummary = $documentAvailable ? 'TRAINING_SCENARIO_MATRIX.md' : 'Training matrix document missing';
$screenHeader = [
    'title' => 'Training Matrix',
    'icon' => 'bi-diagram-3',
];

$statusBadge = static function (string $status): string {
    return match (strtoupper($status)) {
        'IMPLEMENTED' => 'text-bg-success',
        'PLANNED' => 'text-bg-secondary',
        'REVIEW' => 'text-bg-warning',
        'DEFERRED' => 'text-bg-dark',
        default => 'text-bg-light',
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
        <strong><?= h($contextSummary) ?></strong>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Training Paths</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['paths'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Scenarios</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['scenarios'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Implemented</div>
              <div class="fs-4 fw-semibold text-success"><?= h(number_format((int) ($summary['implemented'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Planned</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['planned'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="training-matrix-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Training Matrix Runbook</div>
        <div class="mb-2">Use this screen to review the agreed training paths, role alignment, and scenario rollout status from the training matrix document.</div>
        <div class="small text-muted mb-2">The markdown file is the planning source of truth. Update <code>TRAINING_SCENARIO_MATRIX.md</code>, then refresh this screen to see the revised matrix.</div>
        <div class="small">Filter by path, status, or search text when deciding the next scenarios to build or validate.</div>
      </div>

      <?php if (!$documentAvailable): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <div class="fw-semibold mb-1">Training matrix document not found</div>
          <div>The screen expected to find <code>TRAINING_SCENARIO_MATRIX.md</code> at the application root.</div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Matrix Controls</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2 align-items-end">
            <input type="hidden" name="route" value="training-admin/matrix">
            <div class="col-md-3">
              <label class="form-label" for="trainingMatrixPathFilter">Path</label>
              <select id="trainingMatrixPathFilter" class="form-select" name="path">
                <option value="">All paths</option>
                <?php foreach ($pathOptions as $pathOption): ?>
                  <option value="<?= h((string) $pathOption) ?>" <?= $selectedPath === (string) $pathOption ? 'selected' : '' ?>>
                    <?= h((string) $pathOption) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="trainingMatrixStatusFilter">Status</label>
              <select id="trainingMatrixStatusFilter" class="form-select" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $statusOption): ?>
                  <option value="<?= h((string) $statusOption) ?>" <?= $selectedStatus === (string) $statusOption ? 'selected' : '' ?>>
                    <?= h((string) $statusOption) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="trainingMatrixSearchFilter">Search</label>
              <input id="trainingMatrixSearchFilter" class="form-control" type="text" name="q" value="<?= h($selectedSearch) ?>" placeholder="scenario, permission, role, screen">
            </div>
            <div class="col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-funnel me-1"></i>Filter
              </button>
              <a href="index.php?route=training-admin/matrix" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
              </a>
            </div>
          </form>
          <div class="small text-muted mt-3">
            Source:
            <span class="text-break"><?= h($documentPath) ?></span>
            <?php if ($documentModified > 0): ?>
              <span class="ms-2">Updated <?= h(date('Y-m-d H:i:s', $documentModified)) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Training Paths</h5>
        </div>
        <div class="card-body">
          <?php if ($paths === []): ?>
            <div class="text-center text-muted py-3">No training paths were found in the matrix document.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="text-end">Order</th>
                    <th>Path</th>
                    <th>Name</th>
                    <th>Purpose</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($paths as $path): ?>
                    <tr>
                      <td class="text-end text-muted"><?= h((string) ($path['Order'] ?? '')) ?></td>
                      <td class="fw-semibold"><?= h((string) ($path['Path Code'] ?? '')) ?></td>
                      <td><?= h((string) ($path['Path Name'] ?? '')) ?></td>
                      <td><?= h((string) ($path['Purpose'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Scenario Matrix</h5>
        </div>
        <div class="card-body">
          <div class="small text-muted mb-3">
            Showing <?= h(number_format((int) ($summary['displayed'] ?? count($scenarios)))) ?> of <?= h(number_format((int) ($summary['scenarios'] ?? count($scenarios)))) ?> scenarios.
          </div>
          <?php if ($scenarios === []): ?>
            <div class="text-center text-muted py-3">No scenarios match the current filters.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="text-end">Order</th>
                    <th>Path</th>
                    <th>Scenario</th>
                    <th>Status</th>
                    <th>Screen</th>
                    <th>Permissions</th>
                    <th>Target Roles</th>
                    <th>Prerequisites</th>
                    <th>Training Data</th>
                    <th>Cleanup</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($scenarios as $scenario): ?>
                    <?php $status = strtoupper(trim((string) ($scenario['Status'] ?? ''))); ?>
                    <tr>
                      <td class="text-end text-muted"><?= h((string) ($scenario['Order'] ?? '')) ?></td>
                      <td class="fw-semibold"><?= h((string) ($scenario['Path'] ?? '')) ?></td>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($scenario['Scenario Code'] ?? '')) ?></div>
                        <?php if (!empty($scenario['Menu / Screen'])): ?>
                          <div class="small text-muted"><?= h((string) ($scenario['Menu / Screen'] ?? '')) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge <?= h($statusBadge($status)) ?>"><?= h($status !== '' ? $status : 'Unknown') ?></span>
                      </td>
                      <td><?= h((string) ($scenario['Menu / Screen'] ?? '')) ?></td>
                      <td class="text-break"><?= h((string) ($scenario['Required Permissions'] ?? '')) ?></td>
                      <td><?= h((string) ($scenario['Target Roles'] ?? '')) ?></td>
                      <td><?= h((string) ($scenario['Prerequisites'] ?? '')) ?></td>
                      <td><?= h((string) ($scenario['Training Data'] ?? '')) ?></td>
                      <td><?= h((string) ($scenario['Cleanup'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Role Alignment</h5>
        </div>
        <div class="card-body">
          <?php if ($roles === []): ?>
            <div class="text-center text-muted py-3">No role alignment rows were found in the matrix document.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Role</th>
                    <th>Required Training Paths</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($roles as $role): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($role['Role'] ?? '')) ?></td>
                      <td><?= h((string) ($role['Required Training Paths'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Status Definitions</h5>
        </div>
        <div class="card-body">
          <?php if ($statusValues === []): ?>
            <div class="text-center text-muted py-3">No status definitions were found in the matrix document.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Status</th>
                    <th>Meaning</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($statusValues as $statusValue): ?>
                    <?php $status = strtoupper(trim((string) ($statusValue['Status'] ?? ''))); ?>
                    <tr>
                      <td><span class="badge <?= h($statusBadge($status)) ?>"><?= h($status !== '' ? $status : 'Unknown') ?></span></td>
                      <td><?= h((string) ($statusValue['Meaning'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
