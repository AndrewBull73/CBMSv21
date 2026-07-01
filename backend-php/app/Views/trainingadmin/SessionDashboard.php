<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$session = is_array($session ?? null) ? $session : null;
$participants = is_array($participants ?? null) ? $participants : [];
$managementInstalled = (bool) ($managementInstalled ?? false);
$sessionId = (int) ($sessionId ?? ($session['TrainingSessionID'] ?? 0));
$contextSummary = $session !== null
    ? (string) (($session['SessionTitle'] ?? '') ?: ($session['SessionCode'] ?? 'Training session'))
    : 'No session selected';
$sessionEditUrl = 'index.php?route=training-admin/operations#training-ops-sessions';
if ($session !== null) {
    $sessionEditParams = ['route' => 'training-admin/operations'];
    $pathCode = trim((string) ($session['PathCode'] ?? ''));
    $sessionCode = trim((string) ($session['SessionCode'] ?? ''));
    if ($pathCode !== '') {
        $sessionEditParams['path_code'] = $pathCode;
    }
    if ($sessionCode !== '') {
        $sessionEditParams['edit_session_code'] = $sessionCode;
    }
    $sessionEditUrl = 'index.php?' . http_build_query($sessionEditParams) . '#training-ops-sessions';
}
$completedCount = 0;
$activeCount = 0;
foreach ($participants as $participant) {
    $status = strtolower((string) ($participant['ProgressStatus'] ?? ''));
    if ($status === 'completed') {
        $completedCount++;
    } elseif ($status === 'active') {
        $activeCount++;
    }
}

$screenHeader = [
    'title' => 'Training Session Dashboard',
    'icon' => 'bi-speedometer2',
];

$statusBadge = static function (string $status): string {
    return match (strtolower($status)) {
        'completed' => 'text-bg-success',
        'active', 'running' => 'text-bg-warning',
        'stopped', 'assigned' => 'text-bg-secondary',
        'failed', 'overdue' => 'text-bg-danger',
        'cancelled' => 'text-bg-dark',
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
              <div class="text-muted small">Participants</div>
              <div class="fs-4 fw-semibold"><?= count($participants) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Completed</div>
              <div class="fs-4 fw-semibold text-success"><?= $completedCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Path Steps</div>
              <div class="fs-4 fw-semibold"><?= (int) ($session['PathScenarioCount'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="training-session-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Session Runbook</div>
        <div class="mb-2">Use this dashboard while an instructor is guiding learners through a training path or scenario.</div>
        <div class="small text-muted mb-2">Monitor each participant's latest progress, identify stalled learners, and record instructor sign-off when a learner demonstrates the required outcome.</div>
        <div class="small">Use Training Session Edit to add participants or adjust the session setup.</div>
      </div>

      <?php if (!$managementInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          Run <code>create_training_management_features.sql</code> before using instructor session dashboards.
        </div>
      <?php elseif ($session === null): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          The selected training session could not be found.
        </div>
      <?php endif; ?>

      <?php if ($session !== null): ?>
        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <h5 class="mb-0">Session Detail</h5>
              <a href="<?= h($sessionEditUrl) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil-square me-1"></i>Edit Session
              </a>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-3">
                <div class="text-muted small">Session Code</div>
                <div class="fw-semibold"><?= h((string) ($session['SessionCode'] ?? '')) ?></div>
              </div>
              <div class="col-md-3">
                <div class="text-muted small">Instructor</div>
                <div class="fw-semibold"><?= h((string) ($session['InstructorName'] ?? '')) ?></div>
              </div>
              <div class="col-md-3">
                <div class="text-muted small">Scope</div>
                <div class="fw-semibold"><?= h((string) (($session['PathTitle'] ?? '') ?: ($session['ScenarioTitle'] ?? ''))) ?></div>
              </div>
              <div class="col-md-3">
                <div class="text-muted small">Scheduled</div>
                <div class="fw-semibold"><?= h((string) ($session['ScheduledAt'] ?? '')) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Participants</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Learner</th>
                  <th>Scenario</th>
                  <th>Status</th>
                  <th class="text-end">Progress</th>
                  <th class="text-end">Attempt</th>
                  <th>Last Activity</th>
                  <th class="text-end">Evidence</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($participants === []): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No participants have been added to this session.</td></tr>
                <?php else: ?>
                  <?php foreach ($participants as $participant): ?>
                    <?php
                      $scenarioCode = trim((string) (($participant['ScenarioCode'] ?? '') ?: ($session['ScenarioCode'] ?? '')));
                      $currentStep = (int) ($participant['CurrentStep'] ?? 0);
                      $totalSteps = (int) ($participant['TotalSteps'] ?? 0);
                      $progressLabel = $totalSteps > 0 ? $currentStep . ' / ' . $totalSteps : '';
                    ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) (($participant['DisplayName'] ?? '') ?: ($participant['Username'] ?? ''))) ?></div>
                        <div class="small text-muted">User <?= (int) ($participant['UserID'] ?? 0) ?></div>
                      </td>
                      <td><?= h($scenarioCode) ?></td>
                      <td><span class="badge <?= $statusBadge((string) ($participant['ProgressStatus'] ?? $participant['SessionUserStatus'] ?? '')) ?>"><?= h(ucfirst((string) (($participant['ProgressStatus'] ?? '') ?: ($participant['SessionUserStatus'] ?? 'assigned')))) ?></span></td>
                      <td class="text-end"><?= h($progressLabel) ?></td>
                      <td class="text-end"><?= (int) ($participant['AttemptNo'] ?? 0) ?></td>
                      <td><?= h((string) ($participant['LastActivityAt'] ?? '')) ?></td>
                      <td class="text-end text-nowrap">
                        <?php if ($scenarioCode === ''): ?>
                          <span class="text-muted small">No scenario</span>
                        <?php else: ?>
                          <form method="post" action="index.php?route=training-admin/save-evidence" class="d-inline-flex gap-1 align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="TrainingSessionID" value="<?= $sessionId ?>">
                            <input type="hidden" name="UserID" value="<?= (int) ($participant['UserID'] ?? 0) ?>">
                            <input type="hidden" name="ScenarioCode" value="<?= h($scenarioCode) ?>">
                            <input type="hidden" name="AttemptNo" value="<?= (int) ($participant['AttemptNo'] ?? 0) ?>">
                            <input type="hidden" name="EvidenceType" value="instructor_signoff">
                            <input type="text" name="EvidenceNote" class="form-control form-control-sm" placeholder="Sign-off note">
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                              <i class="bi bi-patch-check me-1"></i>Sign Off
                            </button>
                          </form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            <?php if ($session !== null): ?>
              <a href="<?= h($sessionEditUrl) ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-pencil-square me-1"></i>Edit Session
              </a>
            <?php endif; ?>
            <a href="index.php?route=training-admin/operations#training-ops-sessions" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Training Operations
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
