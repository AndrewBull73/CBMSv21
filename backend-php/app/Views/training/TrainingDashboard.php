<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$modules = is_array($modules ?? null) ? $modules : [];
$trainingSetupRequired = (bool) ($trainingSetupRequired ?? false);
$certificationSetupRequired = (bool) ($certificationSetupRequired ?? false);
$canManageTraining = (bool) ($canManageTraining ?? false);
$trainingProgressScript = (string) ($trainingProgressScript ?? 'backend-php/config/sql/create_tblTrainingProgress.sql');
$certificationScript = (string) ($certificationScript ?? 'backend-php/config/sql/create_training_certification_features.sql');

$overall = [
    'training_total' => 0,
    'training_completed' => 0,
    'training_active' => 0,
    'assigned_total' => 0,
    'assigned_completed' => 0,
    'cert_total' => 0,
    'certified' => 0,
];
foreach ($modules as $module) {
    $trainingCounts = is_array($module['trainingCounts'] ?? null) ? $module['trainingCounts'] : [];
    $certificationCounts = is_array($module['certificationCounts'] ?? null) ? $module['certificationCounts'] : [];
    $overall['training_total'] += (int) ($trainingCounts['assigned'] ?? 0);
    $overall['training_completed'] += (int) ($trainingCounts['assigned_completed'] ?? 0);
    $overall['training_active'] += (int) ($trainingCounts['active'] ?? 0);
    $overall['assigned_total'] += (int) ($trainingCounts['assigned'] ?? 0);
    $overall['assigned_completed'] += (int) ($trainingCounts['assigned_completed'] ?? 0);
    $overall['cert_total'] += (int) ($certificationCounts['total'] ?? 0);
    $overall['certified'] += (int) ($certificationCounts['certified'] ?? 0);
}

$dashboardModes = [
    'self_paced' => [
        'label' => 'Self Paced',
        'match' => 'Self-paced',
        'icon' => 'bi-play-circle',
        'empty' => 'No self paced training is assigned to you yet.',
    ],
    'instructor_led' => [
        'label' => 'Instructor Led',
        'match' => 'Instructor-led',
        'icon' => 'bi-person-video3',
        'empty' => 'No instructor led training is assigned to you yet.',
    ],
];
$modeTotals = array_fill_keys(array_keys($dashboardModes), 0);
$modeCompletedTotals = array_fill_keys(array_keys($dashboardModes), 0);
$certificationRows = [];
foreach ($modules as $module) {
    $moduleName = (string) ($module['module'] ?? 'Uncategorized');
    foreach (array_values(is_array($module['certifications'] ?? null) ? $module['certifications'] : []) as $certification) {
        if (is_array($certification)) {
            $certificationRows[] = [
                'module' => $moduleName,
                'certification' => $certification,
            ];
        }
    }

    $moduleScenarios = array_values(is_array($module['scenarios'] ?? null) ? $module['scenarios'] : []);
    foreach ($moduleScenarios as $scenarioRow) {
        $assignment = is_array($scenarioRow['assignment'] ?? null) ? $scenarioRow['assignment'] : [];
        $assignmentLabel = (string) (($assignment['AssignmentMode'] ?? '') ?: 'Self-paced');
        foreach ($dashboardModes as $modeKey => $modeConfig) {
            if (strcasecmp($assignmentLabel, (string) $modeConfig['match']) === 0) {
                $modeTotals[$modeKey]++;
                if (($scenarioRow['status'] ?? 'not_started') === 'completed') {
                    $modeCompletedTotals[$modeKey]++;
                }
            }
        }
    }
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Training Dashboard</h3>
        <div class="small text-muted mt-1">Your assigned training and related module certification status.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training/scenarios" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-list-check me-1"></i>Training
        </a>
        <?php if ($canManageTraining): ?>
          <a href="index.php?route=training/summary" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-table me-1"></i>Summary
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-body">
      <?php if ($trainingSetupRequired): ?>
        <div class="alert alert-warning">
          Run <code><?= h($trainingProgressScript) ?></code> to enable training progress tracking.
        </div>
      <?php endif; ?>
      <?php if ($certificationSetupRequired): ?>
        <div class="alert alert-warning">
          Run <code><?= h($certificationScript) ?></code> to enable certification tracking.
        </div>
      <?php endif; ?>

      <div id="training-dashboard-overview" class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-3 mb-4">
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Self paced completed</div>
            <div class="fs-4 fw-semibold"><?= h((string) ($modeCompletedTotals['self_paced'] ?? 0)) ?> / <?= h((string) ($modeTotals['self_paced'] ?? 0)) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Instructor led completed</div>
            <div class="fs-4 fw-semibold"><?= h((string) ($modeCompletedTotals['instructor_led'] ?? 0)) ?> / <?= h((string) ($modeTotals['instructor_led'] ?? 0)) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Assigned in progress</div>
            <div class="fs-4 fw-semibold"><?= h((string) $overall['training_active']) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Certified modules</div>
            <div class="fs-4 fw-semibold"><?= h((string) $overall['certified']) ?> / <?= h((string) $overall['cert_total']) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Modules shown</div>
            <div class="fs-4 fw-semibold"><?= h((string) count($modules)) ?></div>
          </div>
        </div>
      </div>

        <?php if ($modules === []): ?>
        <div class="text-center text-muted py-4">
          No training has been assigned to you yet.
          <div class="small mt-1">Assigned paths and scenarios will appear here after a trainer assigns them.</div>
        </div>
      <?php else: ?>
        <ul class="nav nav-tabs mb-3" id="training-dashboard-tabs" role="tablist">
          <?php $isFirstMode = true; ?>
          <?php foreach ($dashboardModes as $modeKey => $modeConfig): ?>
            <li class="nav-item" role="presentation">
              <button
                class="nav-link <?= $isFirstMode ? 'active' : '' ?>"
                id="training-dashboard-<?= h($modeKey) ?>-tab"
                data-bs-toggle="tab"
                data-bs-target="#training-dashboard-<?= h($modeKey) ?>"
                type="button"
                role="tab"
                aria-controls="training-dashboard-<?= h($modeKey) ?>"
                aria-selected="<?= $isFirstMode ? 'true' : 'false' ?>"
              >
                <i class="bi <?= h((string) $modeConfig['icon']) ?> me-1"></i><?= h((string) $modeConfig['label']) ?>
                <span class="badge text-bg-light border ms-1"><?= h((string) ($modeTotals[$modeKey] ?? 0)) ?></span>
              </button>
            </li>
            <?php $isFirstMode = false; ?>
          <?php endforeach; ?>
          <li class="nav-item" role="presentation">
            <button
              class="nav-link"
              id="training-dashboard-certifications-tab"
              data-bs-toggle="tab"
              data-bs-target="#training-dashboard-certifications"
              type="button"
              role="tab"
              aria-controls="training-dashboard-certifications"
              aria-selected="false"
            >
              <i class="bi bi-award me-1"></i>Certifications
              <span class="badge text-bg-light border ms-1"><?= h((string) count($certificationRows)) ?></span>
            </button>
          </li>
        </ul>

        <div id="training-dashboard-modules" class="tab-content">
          <?php $isFirstMode = true; ?>
          <?php foreach ($dashboardModes as $modeKey => $modeConfig): ?>
            <div
              class="tab-pane fade <?= $isFirstMode ? 'show active' : '' ?>"
              id="training-dashboard-<?= h($modeKey) ?>"
              role="tabpanel"
              aria-labelledby="training-dashboard-<?= h($modeKey) ?>-tab"
              tabindex="0"
            >
              <?php if (($modeTotals[$modeKey] ?? 0) <= 0): ?>
                <div class="text-center text-muted py-4">
                  <?= h((string) $modeConfig['empty']) ?>
                </div>
              <?php else: ?>
                <div class="row g-3">
                  <?php foreach ($modules as $module): ?>
                    <?php
                    $moduleName = (string) ($module['module'] ?? 'Uncategorized');
                    $certificationCounts = is_array($module['certificationCounts'] ?? null) ? $module['certificationCounts'] : [];
                    $allModuleScenarios = array_values(is_array($module['scenarios'] ?? null) ? $module['scenarios'] : []);
                    $moduleScenarios = array_values(array_filter($allModuleScenarios, static function (array $scenarioRow) use ($modeConfig): bool {
                        $assignment = is_array($scenarioRow['assignment'] ?? null) ? $scenarioRow['assignment'] : [];
                        $assignmentLabel = (string) (($assignment['AssignmentMode'] ?? '') ?: 'Self-paced');
                        return strcasecmp($assignmentLabel, (string) $modeConfig['match']) === 0;
                    }));
                    if ($moduleScenarios === []) {
                        continue;
                    }
                    $assignedTotal = count($moduleScenarios);
                    $assignedCompleted = 0;
                    $assignedInProgress = 0;
                    foreach ($moduleScenarios as $scenarioRow) {
                        $scenarioStatusForCount = (string) ($scenarioRow['status'] ?? 'not_started');
                        if ($scenarioStatusForCount === 'completed') {
                            $assignedCompleted++;
                        } elseif (in_array($scenarioStatusForCount, ['active', 'stopped', 'in_progress'], true)) {
                            $assignedInProgress++;
                        }
                    }
                    $trainingPercent = $assignedTotal > 0 ? (int) round(($assignedCompleted / $assignedTotal) * 100) : 0;
                    $nextScenario = null;
                    foreach ($moduleScenarios as $scenarioRow) {
                        if (($scenarioRow['status'] ?? 'not_started') !== 'completed') {
                            $nextScenario = $scenarioRow;
                            break;
                        }
                    }
                    ?>
                    <div class="col-xl-6">
                      <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                          <div>
                            <div class="fw-semibold"><?= h($moduleName) ?></div>
                            <div class="small text-muted">
                              <?= h((string) $assignedCompleted) ?> of <?= h((string) $assignedTotal) ?> <?= h(strtolower((string) $modeConfig['label'])) ?> scenarios completed
                            </div>
                          </div>
                          <span class="badge text-bg-light border"><?= h((string) $trainingPercent) ?>%</span>
                        </div>
                        <div class="card-body">
                          <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= h((string) $trainingPercent) ?>%;" aria-valuenow="<?= h((string) $trainingPercent) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>

                          <div class="row g-2 small mb-3">
                            <div class="col-4">
                              <div class="text-muted">Assigned</div>
                              <div class="fw-semibold"><?= h((string) $assignedTotal) ?></div>
                            </div>
                            <div class="col-4">
                              <div class="text-muted">In progress</div>
                              <div class="fw-semibold"><?= h((string) $assignedInProgress) ?></div>
                            </div>
                            <div class="col-4">
                              <div class="text-muted">Certified</div>
                              <div class="fw-semibold"><?= h((string) ((int) ($certificationCounts['certified'] ?? 0))) ?> / <?= h((string) ((int) ($certificationCounts['total'] ?? 0))) ?></div>
                            </div>
                          </div>

                  <?php if ($nextScenario !== null): ?>
                    <?php
                    $nextScenarioData = is_array($nextScenario['scenario'] ?? null) ? $nextScenario['scenario'] : [];
                    $nextScenarioTitle = (string) ($nextScenarioData['title'] ?? $nextScenarioData['id'] ?? 'Training scenario');
                    $nextScenarioStatus = (string) ($nextScenario['status'] ?? 'not_started');
                    $nextAssignment = is_array($nextScenario['assignment'] ?? null) ? $nextScenario['assignment'] : [];
                    $nextAssignmentLabel = (string) (($nextAssignment['AssignmentMode'] ?? '') ?: 'Self-paced');
                    $nextAssignmentBadgeClass = $nextAssignmentLabel === 'Instructor-led' ? 'text-bg-warning' : 'text-bg-info';
                    ?>
                    <div class="border rounded p-3 mb-3">
                      <div class="small text-muted mb-1"><?= $nextScenarioStatus === 'active' ? 'Continue assigned training' : 'Next assigned training' ?></div>
                      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="fw-semibold"><?= h($nextScenarioTitle) ?></div>
                        <span class="badge text-bg-info">Assigned</span>
                        <span class="badge <?= h($nextAssignmentBadgeClass) ?>"><?= h($nextAssignmentLabel) ?></span>
                      </div>
                      <a href="<?= h((string) ($nextScenario['startUrl'] ?? 'index.php?route=training/scenarios')) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-play-circle me-1"></i><?= $nextScenarioStatus === 'active' ? 'Resume' : 'Open' ?>
                      </a>
                    </div>
                  <?php endif; ?>

                  <?php if ($moduleScenarios !== []): ?>
                    <div class="table-responsive mb-3">
                      <table class="table table-sm align-middle mb-0">
                        <thead>
                          <tr>
                            <th>Assigned Training</th>
                            <th>Mode</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($moduleScenarios as $scenarioRow): ?>
                            <?php
                            $scenarioData = is_array($scenarioRow['scenario'] ?? null) ? $scenarioRow['scenario'] : [];
                            $assignment = is_array($scenarioRow['assignment'] ?? null) ? $scenarioRow['assignment'] : [];
                            $scenarioTitle = (string) ($scenarioData['title'] ?? $scenarioData['id'] ?? 'Training scenario');
                            $scenarioStatus = (string) ($scenarioRow['status'] ?? 'not_started');
                            $assignmentLabel = (string) (($assignment['AssignmentMode'] ?? '') ?: 'Self-paced');
                            $assignmentBadgeClass = $assignmentLabel === 'Instructor-led' ? 'text-bg-warning' : 'text-bg-info';
                            ?>
                            <tr>
                              <td>
                                <div class="fw-semibold"><?= h($scenarioTitle) ?></div>
                                <?php if (($assignment['PathTitle'] ?? '') !== ''): ?>
                                  <div class="small text-muted"><?= h((string) ($assignment['PathTitle'] ?? '')) ?></div>
                                <?php endif; ?>
                              </td>
                              <td><span class="badge <?= h($assignmentBadgeClass) ?>"><?= h($assignmentLabel) ?></span></td>
                              <td><?= h(ucfirst(str_replace('_', ' ', $scenarioStatus))) ?></td>
                              <td class="text-end">
                                <a href="<?= h((string) ($scenarioRow['startUrl'] ?? 'index.php?route=training/scenarios')) ?>" class="btn btn-sm btn-outline-primary">
                                  <i class="bi bi-play-circle me-1"></i><?= $scenarioStatus === 'active' ? 'Resume' : 'Open' ?>
                                </a>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>

                </div>
              </div>
            </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <?php $isFirstMode = false; ?>
          <?php endforeach; ?>

          <div
            class="tab-pane fade"
            id="training-dashboard-certifications"
            role="tabpanel"
            aria-labelledby="training-dashboard-certifications-tab"
            tabindex="0"
          >
            <?php if ($certificationRows === []): ?>
              <div class="text-center text-muted py-4">
                No certifications are available for your assigned training modules yet.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Module</th>
                      <th>Certification</th>
                      <th>Status</th>
                      <th>Score</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($certificationRows as $certificationRow): ?>
                      <?php
                      $certification = is_array($certificationRow['certification'] ?? null) ? $certificationRow['certification'] : [];
                      $certCode = (string) ($certification['CertificationCode'] ?? '');
                      $latestStatus = strtolower((string) ($certification['LatestStatus'] ?? ''));
                      $latestPassed = (int) ($certification['LatestPassedFlag'] ?? 0) === 1;
                      $latestScore = $certification['LatestScorePercent'] ?? null;
                      $questionCount = (int) ($certification['QuestionCount'] ?? 0);
                      $statusLabel = $latestStatus === 'submitted'
                          ? ($latestPassed ? 'Certified' : 'Not certified')
                          : 'Not attempted';
                      $statusClass = $latestStatus === 'submitted'
                          ? ($latestPassed ? 'text-bg-success' : 'text-bg-danger')
                          : 'text-bg-secondary';
                      ?>
                      <tr>
                        <td><?= h((string) ($certificationRow['module'] ?? 'Uncategorized')) ?></td>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($certification['CertificationTitle'] ?? $certCode)) ?></div>
                          <div class="small text-muted"><?= h((string) ((float) ($certification['PassPercent'] ?? 80))) ?>% pass</div>
                        </td>
                        <td><span class="badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
                        <td><?= $latestScore !== null ? h((string) $latestScore) . '%' : '-' ?></td>
                        <td class="text-end">
                          <form method="post" action="index.php?route=training-certifications/start" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="certification_code" value="<?= h($certCode) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" <?= $questionCount <= 0 ? 'disabled' : '' ?>>
                              <i class="bi bi-play-circle me-1"></i><?= $latestStatus === 'submitted' ? 'Retake' : 'Start' ?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
