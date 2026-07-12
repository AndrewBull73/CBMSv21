<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$sessions = array_values(is_array($sessions ?? null) ? $sessions : []);
$participants = array_values(is_array($participants ?? null) ? $participants : []);
$paths = array_values(is_array($paths ?? null) ? $paths : []);
$filters = is_array($filters ?? null) ? $filters : [];
$managementInstalled = (bool) ($managementInstalled ?? false);

$statusFilter = (string) ($filters['status'] ?? '');
$pathFilter = (string) ($filters['path_code'] ?? '');
$searchFilter = (string) ($filters['q'] ?? '');

$summary = [
    'sessions' => count($sessions),
    'participants' => 0,
    'completed' => 0,
    'active' => 0,
    'evidence' => 0,
];
foreach ($sessions as $session) {
    $summary['participants'] += (int) ($session['ParticipantCount'] ?? 0);
    $summary['completed'] += (int) ($session['CompletedParticipantCount'] ?? 0);
    $summary['active'] += (int) ($session['ActiveParticipantCount'] ?? 0);
    $summary['evidence'] += (int) ($session['EvidenceCount'] ?? 0);
}

$participantsBySession = [];
foreach ($participants as $participant) {
    $sessionId = (int) ($participant['TrainingSessionID'] ?? 0);
    if ($sessionId <= 0) {
        continue;
    }
    $participantsBySession[$sessionId] ??= [];
    $participantsBySession[$sessionId][] = $participant;
}

$statusBadge = static function (string $status): string {
    return match (strtolower($status)) {
        'completed' => 'text-bg-success',
        'active', 'running' => 'text-bg-warning',
        'planned' => 'text-bg-info',
        'cancelled' => 'text-bg-dark',
        default => 'text-bg-secondary',
    };
};

$progressBadge = static function (string $status): string {
    return match (strtolower($status)) {
        'completed' => 'text-bg-success',
        'active', 'in_progress' => 'text-bg-warning',
        'stopped' => 'text-bg-secondary',
        'failed', 'overdue' => 'text-bg-danger',
        default => 'text-bg-light',
    };
};

$screenHeader = [
    'title' => 'Training Session Summary',
    'icon' => 'bi-calendar2-week',
    'actions' => [
        [
            'label' => 'Training Operations',
            'url' => 'index.php?route=training-admin/operations#training-ops-sessions',
            'class' => 'btn btn-sm btn-outline-secondary',
            'icon' => 'bi-clipboard2-check',
        ],
    ],
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!$managementInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          Run <code>create_training_management_features.sql</code> before using Training Session Summary.
        </div>
      <?php endif; ?>

      <form method="get" class="row g-2 align-items-end mb-4">
        <input type="hidden" name="route" value="training-admin/session-summary">
        <div class="col-md-4">
          <label for="TrainingSessionSummarySearch" class="form-label">Search</label>
          <input type="text" name="q" id="TrainingSessionSummarySearch" value="<?= h($searchFilter) ?>" class="form-control" placeholder="Session, instructor, course, scenario">
        </div>
        <div class="col-md-3">
          <label for="TrainingSessionSummaryStatus" class="form-label">Status</label>
          <select name="status" id="TrainingSessionSummaryStatus" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['planned', 'active', 'completed', 'cancelled'] as $statusOption): ?>
              <option value="<?= h($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= h(ucfirst($statusOption)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="TrainingSessionSummaryPath" class="form-label">Course / Path</label>
          <select name="path_code" id="TrainingSessionSummaryPath" class="form-select">
            <option value="">All courses/paths</option>
            <?php foreach ($paths as $path): ?>
              <?php $pathCode = (string) ($path['PathCode'] ?? ''); ?>
              <option value="<?= h($pathCode) ?>" <?= $pathFilter === $pathCode ? 'selected' : '' ?>>
                <?= h((string) (($path['PathTitle'] ?? '') ?: $pathCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Apply</button>
        </div>
      </form>

      <div class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-3 mb-4">
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Sessions</div>
            <div class="fs-4 fw-semibold"><?= h((string) $summary['sessions']) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Participants</div>
            <div class="fs-4 fw-semibold"><?= h((string) $summary['participants']) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Active participants</div>
            <div class="fs-4 fw-semibold"><?= h((string) $summary['active']) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Completed participants</div>
            <div class="fs-4 fw-semibold"><?= h((string) $summary['completed']) ?></div>
          </div>
        </div>
        <div class="col">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Evidence records</div>
            <div class="fs-4 fw-semibold"><?= h((string) $summary['evidence']) ?></div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Session</th>
              <th>Instructor</th>
              <th>Scope</th>
              <th>Scheduled</th>
              <th>Status</th>
              <th class="text-end">Participants</th>
              <th class="text-end">Completed</th>
              <th class="text-end">Evidence</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($sessions === []): ?>
              <tr>
                <td colspan="9" class="text-center text-muted py-4">No training sessions match the current filters.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($sessions as $session): ?>
                <?php
                $sessionId = (int) ($session['TrainingSessionID'] ?? 0);
                $sessionParticipants = array_values($participantsBySession[$sessionId] ?? []);
                $participantCount = (int) ($session['ParticipantCount'] ?? 0);
                $completedParticipantCount = (int) ($session['CompletedParticipantCount'] ?? 0);
                $completionPercent = $participantCount > 0 ? (int) round(($completedParticipantCount / $participantCount) * 100) : 0;
                $editParams = ['route' => 'training-admin/operations'];
                $sessionCode = trim((string) ($session['SessionCode'] ?? ''));
                $pathCode = trim((string) ($session['PathCode'] ?? ''));
                if ($pathCode !== '') {
                    $editParams['path_code'] = $pathCode;
                }
                if ($sessionCode !== '') {
                    $editParams['edit_session_code'] = $sessionCode;
                }
                $editUrl = 'index.php?' . http_build_query($editParams) . '#training-ops-sessions';
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($session['SessionTitle'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h($sessionCode) ?></div>
                  </td>
                  <td><?= h((string) (($session['InstructorName'] ?? '') ?: 'Unassigned')) ?></td>
                  <td>
                    <div><?= h((string) (($session['PathTitle'] ?? '') ?: ($session['ScenarioTitle'] ?? ''))) ?></div>
                    <?php if ((int) ($session['PathScenarioCount'] ?? 0) > 0): ?>
                      <div class="small text-muted"><?= (int) ($session['PathScenarioCount'] ?? 0) ?> path scenario<?= (int) ($session['PathScenarioCount'] ?? 0) === 1 ? '' : 's' ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string) ($session['ScheduledAt'] ?? '')) ?></td>
                  <td><span class="badge <?= h($statusBadge((string) ($session['Status'] ?? 'planned'))) ?>"><?= h(ucfirst((string) ($session['Status'] ?? 'planned'))) ?></span></td>
                  <td class="text-end"><?= h((string) $participantCount) ?></td>
                  <td class="text-end">
                    <div class="fw-semibold"><?= h((string) $completedParticipantCount) ?> / <?= h((string) $participantCount) ?></div>
                    <div class="small text-muted"><?= h((string) $completionPercent) ?>%</div>
                  </td>
                  <td class="text-end">
                    <div><?= h((string) ((int) ($session['EvidenceCount'] ?? 0))) ?></div>
                    <?php if (($session['LastEvidenceAt'] ?? null) !== null): ?>
                      <div class="small text-muted"><?= h((string) ($session['LastEvidenceAt'] ?? '')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= h($editUrl) ?>">
                      <i class="bi bi-pencil-square me-1"></i>Edit
                    </a>
                    <a class="btn btn-sm btn-outline-primary" href="index.php?route=training-admin/session-dashboard&amp;session_id=<?= h((string) $sessionId) ?>">
                      <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <form method="post" action="index.php?route=training-admin/reset-session" class="d-inline" onsubmit="return confirm('Reset training progress for all participants in this session? The session, participants, and evidence notes will be kept.');">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="TrainingSessionID" value="<?= h((string) $sessionId) ?>">
                      <input type="hidden" name="return_status" value="<?= h($statusFilter) ?>">
                      <input type="hidden" name="return_path_code" value="<?= h($pathFilter) ?>">
                      <input type="hidden" name="return_q" value="<?= h($searchFilter) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                      </button>
                    </form>
                  </td>
                </tr>
                <tr>
                  <td colspan="9" class="bg-light">
                    <?php if ($sessionParticipants === []): ?>
                      <div class="text-muted small py-2">No participants are attached to this session.</div>
                    <?php else: ?>
                      <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                          <thead>
                            <tr>
                              <th>Learner</th>
                              <th>Session Status</th>
                              <th>Progress Status</th>
                              <th class="text-end">Progress</th>
                              <th class="text-end">Attempt</th>
                              <th>Last Activity</th>
                              <th class="text-end">Evidence</th>
                              <th class="text-end">Action</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($sessionParticipants as $participant): ?>
                              <?php
                              $participantUserId = (int) ($participant['UserID'] ?? 0);
                              $progressStatus = strtolower(trim((string) ($participant['ProgressStatus'] ?? '')));
                              $progressStatusLabel = $progressStatus !== '' ? ucfirst(str_replace('_', ' ', $progressStatus)) : 'Not started';
                              $currentStep = (int) ($participant['CurrentStep'] ?? 0);
                              $totalSteps = (int) ($participant['TotalSteps'] ?? 0);
                              $completedScenarioCount = (int) ($participant['CompletedScenarioCount'] ?? 0);
                              $pathScenarioCount = (int) ($session['PathScenarioCount'] ?? 0);
                              if ($pathScenarioCount > 0) {
                                  $progressText = $completedScenarioCount . ' / ' . $pathScenarioCount . ' scenarios';
                              } elseif ($totalSteps > 0) {
                                  $progressText = $currentStep . ' / ' . $totalSteps . ' steps';
                              } elseif ($progressStatus === 'completed') {
                                  $progressText = 'Complete';
                              } else {
                                  $progressText = '-';
                              }
                              ?>
                              <tr>
                                <td>
                                  <div class="fw-semibold"><?= h((string) ($participant['LearnerName'] ?? '')) ?></div>
                                  <div class="small text-muted">
                                    <?= h((string) ($participant['Username'] ?? '')) ?>
                                    <?php if (trim((string) ($participant['Email'] ?? '')) !== ''): ?>
                                      &middot; <?= h((string) ($participant['Email'] ?? '')) ?>
                                    <?php endif; ?>
                                  </div>
                                </td>
                                <td><span class="badge <?= h($statusBadge((string) ($participant['SessionUserStatus'] ?? 'assigned'))) ?>"><?= h(ucfirst((string) ($participant['SessionUserStatus'] ?? 'assigned'))) ?></span></td>
                                <td><span class="badge <?= h($progressBadge($progressStatus)) ?>"><?= h($progressStatusLabel) ?></span></td>
                                <td class="text-end"><?= h($progressText) ?></td>
                                <td class="text-end"><?= (int) ($participant['AttemptNo'] ?? 0) > 0 ? h((string) ((int) ($participant['AttemptNo'] ?? 0))) : '-' ?></td>
                                <td><?= h((string) (($participant['LastActivityAt'] ?? '') ?: '-')) ?></td>
                                <td class="text-end"><?= h((string) ((int) ($participant['EvidenceCount'] ?? 0))) ?></td>
                                <td class="text-end">
                                  <?php if ($participantUserId > 0): ?>
                                    <form method="post" action="index.php?route=training-admin/reset-session-participant" class="d-inline" onsubmit="return confirm('Reset training progress for this participant in the selected session? Evidence notes will be kept.');">
                                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                      <input type="hidden" name="TrainingSessionID" value="<?= h((string) $sessionId) ?>">
                                      <input type="hidden" name="UserID" value="<?= h((string) $participantUserId) ?>">
                                      <input type="hidden" name="return_status" value="<?= h($statusFilter) ?>">
                                      <input type="hidden" name="return_path_code" value="<?= h($pathFilter) ?>">
                                      <input type="hidden" name="return_q" value="<?= h($searchFilter) ?>">
                                      <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                      </button>
                                    </form>
                                  <?php endif; ?>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
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
