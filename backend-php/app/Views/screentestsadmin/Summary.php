<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$assignmentsInstalled = (bool) ($assignmentsInstalled ?? false);
$createAssignmentsScript = (string) ($createAssignmentsScript ?? '');
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'status' => ''];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$overall = is_array($overall ?? null) ? $overall : [];
$moduleSummary = array_values(is_array($moduleSummary ?? null) ? $moduleSummary : []);
$userSummary = array_values(is_array($userSummary ?? null) ? $userSummary : []);
$assignmentRows = array_values(is_array($assignmentRows ?? null) ? $assignmentRows : []);
$exportQuery = http_build_query([
    'route' => 'screen-tests-admin/export-summary-excel',
    'q' => (string) ($filters['q'] ?? ''),
    'module' => (string) ($filters['module'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
]);

$statusBadge = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed' => 'text-bg-success',
        'in_progress' => 'text-bg-primary',
        default => 'text-bg-light border text-dark',
    };
};
$resultBadge = static function (string $result): string {
    return match (strtolower(trim($result))) {
        'passed' => 'text-bg-success',
        'failed' => 'text-bg-danger',
        'blocked' => 'text-bg-warning',
        default => 'text-bg-light border text-dark',
    };
};
$labelStatus = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'completed' => 'Completed',
        'in_progress' => 'In Progress',
        default => 'Not Started',
    };
};
$labelResult = static function (string $result): string {
    return match (strtolower(trim($result))) {
        'passed' => 'Passed',
        'failed' => 'Failed',
        'blocked' => 'Blocked',
        default => 'Not Run',
    };
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Testing Summary</h3>
        <div class="small text-muted mt-1">Review assigned test-script progress by module, tester, and script.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?<?= h($exportQuery) ?>" class="btn btn-sm btn-outline-success <?= $assignmentsInstalled ? '' : 'disabled' ?>" <?= $assignmentsInstalled ? '' : 'aria-disabled="true"' ?>><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
        <a href="index.php?route=screen-tests-admin/assignments" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-check me-1"></i>Assign Scripts</a>
        <a href="index.php?route=screen-tests/summary" class="btn btn-sm btn-outline-secondary"><i class="bi bi-table me-1"></i>Run History</a>
      </div>
    </div>
    <div class="card-body">
      <?php
      $testingQuickLinksMode = 'admin';
      require __DIR__ . '/../screentests/_TestingQuickLinks.php';
      $testingHelperTitle = 'How to monitor testing progress';
      $testingHelperItems = [
          'Start with the overall counters to identify not-started, in-progress, completed, and overdue assignments.',
          'Use <strong>Progress by Module</strong> and <strong>Progress by Tester</strong> to find coverage gaps before sign-off.',
          'Use the detail table to inspect latest results, defect references, and reset individual assignments for retesting.',
      ];
      require __DIR__ . '/../screentests/_TestingHelperInstructions.php';
      ?>

      <?php if (!$assignmentsInstalled): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1">Assignment storage is not installed</div>
          <div class="small">Run <code><?= h($createAssignmentsScript) ?></code> before using the testing summary.</div>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-4">
        <input type="hidden" name="route" value="screen-tests-admin/summary">
        <div class="col-lg-4">
          <label class="form-label" for="testing-summary-search">Search</label>
          <input id="testing-summary-search" type="search" name="q" class="form-control" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search user, script, module, result, or defect">
        </div>
        <div class="col-lg-3">
          <label class="form-label" for="testing-summary-module">Module</label>
          <select id="testing-summary-module" name="module" class="form-select">
            <option value="">All modules</option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>><?= h((string) $moduleOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3">
          <label class="form-label" for="testing-summary-status">Assignment Status</label>
          <select id="testing-summary-status" name="status" class="form-select">
            <option value="">All statuses</option>
            <option value="assigned" <?= ((string) ($filters['status'] ?? '') === 'assigned') ? 'selected' : '' ?>>Not Started</option>
            <option value="in_progress" <?= ((string) ($filters['status'] ?? '') === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
            <option value="completed" <?= ((string) ($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>
        <div class="col-lg-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-outline-primary flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
          <a href="index.php?route=screen-tests-admin/summary" class="btn btn-outline-secondary flex-fill">Reset</a>
        </div>
      </form>

      <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-4">
        <?php foreach ([
            'Total Assigned' => 'total',
            'Not Started' => 'not_started',
            'In Progress' => 'in_progress',
            'Completed' => 'completed',
            'Overdue' => 'overdue',
        ] as $label => $key): ?>
          <div class="col">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted"><?= h($label) ?></div>
              <div class="fs-4 fw-semibold"><?= h((string) (int) ($overall[$key] ?? 0)) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-xl-6">
          <h4 class="h5 mb-3">Progress by Module</h4>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Module</th>
                  <th>Total</th>
                  <th>Completed</th>
                  <th>Overdue</th>
                  <th style="width: 35%;">Completion</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($moduleSummary === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">No assignments found.</td></tr>
                <?php else: ?>
                  <?php foreach ($moduleSummary as $row): ?>
                    <?php $percent = max(0, min(100, (int) ($row['completion_percent'] ?? 0))); ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($row['module'] ?? '')) ?></td>
                      <td><?= h((string) (int) ($row['total'] ?? 0)) ?></td>
                      <td><?= h((string) (int) ($row['completed'] ?? 0)) ?></td>
                      <td><?= h((string) (int) ($row['overdue'] ?? 0)) ?></td>
                      <td>
                        <div class="progress" role="progressbar" aria-valuenow="<?= h((string) $percent) ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
                          <div class="progress-bar" style="width: <?= h((string) $percent) ?>%;"></div>
                        </div>
                        <div class="small text-muted mt-1"><?= h((string) $percent) ?>%</div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="col-xl-6">
          <h4 class="h5 mb-3">Progress by Tester</h4>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Tester</th>
                  <th>Total</th>
                  <th>Completed</th>
                  <th>Overdue</th>
                  <th style="width: 35%;">Completion</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($userSummary === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">No assignments found.</td></tr>
                <?php else: ?>
                  <?php foreach ($userSummary as $row): ?>
                    <?php $percent = max(0, min(100, (int) ($row['completion_percent'] ?? 0))); ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($row['user_name'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($row['username'] ?? '')) ?></div>
                      </td>
                      <td><?= h((string) (int) ($row['total'] ?? 0)) ?></td>
                      <td><?= h((string) (int) ($row['completed'] ?? 0)) ?></td>
                      <td><?= h((string) (int) ($row['overdue'] ?? 0)) ?></td>
                      <td>
                        <div class="progress" role="progressbar" aria-valuenow="<?= h((string) $percent) ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
                          <div class="progress-bar" style="width: <?= h((string) $percent) ?>%;"></div>
                        </div>
                        <div class="small text-muted mt-1"><?= h((string) $percent) ?>%</div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <h4 class="h5 mb-3">Assignment Detail</h4>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Tester</th>
              <th>Script</th>
              <th>Module</th>
              <th>Due</th>
              <th>Status</th>
              <th>Latest Result</th>
              <th>Latest Run</th>
              <th>Defect</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($assignmentRows === []): ?>
              <tr><td colspan="9" class="text-center text-muted py-3">No assignments found.</td></tr>
            <?php else: ?>
              <?php foreach ($assignmentRows as $row): ?>
                <?php
                $assignmentId = (int) ($row['ScreenTestAssignmentID'] ?? 0);
                $displayName = trim((string) ($row['DisplayName'] ?? ''));
                if ($displayName === '') {
                    $displayName = trim((string) ($row['Username'] ?? ('User #' . (int) ($row['UserID'] ?? 0))));
                }
                $status = strtolower((string) ($row['AssignmentStatus'] ?? 'assigned'));
                $result = strtolower((string) ($row['RunResult'] ?? ''));
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($displayName) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['Username'] ?? '')) ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['ScenarioTitle'] ?? $row['ScenarioCode'] ?? '')) ?></div>
                    <div class="small text-muted"><code><?= h((string) ($row['ScenarioCode'] ?? '')) ?></code></div>
                  </td>
                  <td><?= h((string) ($row['ModuleName'] ?? '')) ?></td>
                  <td><?= h(trim((string) ($row['DueDate'] ?? '')) !== '' ? (string) $row['DueDate'] : 'n/a') ?></td>
                  <td><span class="badge rounded-pill <?= h($statusBadge($status)) ?>"><?= h($labelStatus($status)) ?></span></td>
                  <td><span class="badge rounded-pill <?= h($resultBadge($result)) ?>"><?= h($labelResult($result)) ?></span></td>
                  <td>
                    <?php if ((int) ($row['ScreenTestRunID'] ?? 0) <= 0): ?>
                      <span class="text-muted small">No run yet</span>
                    <?php else: ?>
                      <a href="index.php?route=screen-tests/summary&amp;scenario_code=<?= urlencode((string) ($row['ScenarioCode'] ?? '')) ?>&amp;q=<?= urlencode((string) ($row['Username'] ?? '')) ?>">
                        <?= h((string) ($row['LatestRunCompletedAt'] ?? '')) ?>
                      </a>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string) ($row['DefectReference'] ?? '')) ?></td>
                  <td class="text-end">
                    <?php if ($assignmentId > 0 && $status !== 'assigned'): ?>
                      <form method="post" action="index.php?route=screen-tests-admin/reset-assignment" onsubmit="return confirm('Reset this assignment to Not Started for retesting?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ScreenTestAssignmentID" value="<?= h((string) $assignmentId) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning">
                          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">n/a</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
