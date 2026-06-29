<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$paths = is_array($paths ?? null) ? $paths : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$sessions = is_array($sessions ?? null) ? $sessions : [];
$stuckEvents = is_array($stuckEvents ?? null) ? $stuckEvents : [];
$cleanupTags = is_array($cleanupTags ?? null) ? $cleanupTags : [];
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$catalogInstalled = (bool) ($catalogInstalled ?? false);
$managementInstalled = (bool) ($managementInstalled ?? false);
$contextSummary = 'Training management workspace';
$screenHeader = [
    'title' => 'Training Operations',
    'icon' => 'bi-clipboard2-check',
];

$statusBadge = static function (string $status): string {
    return match (strtolower($status)) {
        'completed', 'resolved', 'cleaned' => 'text-bg-success',
        'active', 'in_progress', 'running' => 'text-bg-warning',
        'failed', 'overdue' => 'text-bg-danger',
        'cancelled', 'archived' => 'text-bg-dark',
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
        <strong><?= h($contextSummary) ?></strong>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Training Paths</div>
              <div class="fs-4 fw-semibold"><?= count($paths) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Open Assignments</div>
              <div class="fs-4 fw-semibold"><?= count($assignments) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Sessions</div>
              <div class="fs-4 fw-semibold"><?= count($sessions) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Open Support</div>
              <div class="fs-4 fw-semibold"><?= count($stuckEvents) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="training-operations-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Training Operations Runbook</div>
        <div class="mb-2">Use this screen to organise self-training paths, assign scenarios, prepare instructor-led sessions, monitor learners who request help, and track cleanup evidence.</div>
        <div class="small text-muted mb-2">Create paths for repeatable learning journeys, use assignments for individual users, and use sessions when an instructor is taking a group through the same scenario.</div>
        <div class="small">Run validation before structured training so missing routes, target element IDs, or samples can be corrected before learners begin.</div>
      </div>

      <?php if (!$catalogInstalled || !$managementInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <div class="fw-semibold mb-1">Training setup is incomplete</div>
          <?php if (!$catalogInstalled): ?><div>Run <code>create_training_scenario_catalog.sql</code> before maintaining training scenarios.</div><?php endif; ?>
          <?php if (!$managementInstalled): ?><div>Run <code>create_training_management_features.sql</code> before using paths, sessions, assignments, evidence, and support tracking.</div><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Training Path</h5>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?route=training-admin/save-path" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-2">
              <label class="form-label">Path Code</label>
              <input type="text" name="PathCode" class="form-control" placeholder="BASIC-USERS" required <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Title</label>
              <input type="text" name="PathTitle" class="form-control" placeholder="Basic Users Training" required <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-2">
              <label class="form-label">Audience</label>
              <input type="text" name="Audience" class="form-control" placeholder="New users" <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-1">
              <label class="form-label">Sort</label>
              <input type="number" name="SortOrder" class="form-control" value="0" min="0" step="10" <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Scenario Codes</label>
              <input type="text" name="ScenarioCodes" class="form-control" placeholder="USERS_CREATE_DEMO, USERS_EDIT_RECORD" <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="Description" class="form-control" rows="2" <?= !$managementInstalled ? 'disabled' : '' ?>></textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
              <div class="form-check me-auto">
                <input id="training-path-active" type="checkbox" name="ActiveFlag" class="form-check-input" checked <?= !$managementInstalled ? 'disabled' : '' ?>>
                <label class="form-check-label" for="training-path-active">Active path</label>
              </div>
              <button type="submit" class="btn btn-sm btn-primary" <?= !$managementInstalled ? 'disabled' : '' ?>>
                <i class="bi bi-save me-1"></i>Save Path
              </button>
              <a href="index.php?route=training-admin/validation" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-check2-square me-1"></i>Validation
              </a>
            </div>
          </form>

          <div class="table-responsive mt-3">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Path</th>
                  <th>Audience</th>
                  <th class="text-end">Scenarios</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($paths === []): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No training paths have been configured.</td></tr>
                <?php else: ?>
                  <?php foreach ($paths as $path): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($path['PathTitle'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($path['PathCode'] ?? '')) ?></div>
                      </td>
                      <td><?= h((string) ($path['Audience'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($path['ScenarioCount'] ?? 0) ?></td>
                      <td><span class="badge <?= ((int) ($path['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= ((int) ($path['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Assignments And Sessions</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-xl-6">
              <form method="post" action="index.php?route=training-admin/save-assignment" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-6">
                  <label class="form-label">User IDs</label>
                  <input type="text" name="UserIDs" class="form-control" placeholder="12, 15, 18" required <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Due Date</label>
                  <input type="date" name="DueDate" class="form-control" <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Path</label>
                  <select name="PathCode" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <option value="">No path</option>
                    <?php foreach ($paths as $path): ?>
                      <option value="<?= h((string) ($path['PathCode'] ?? '')) ?>"><?= h((string) ($path['PathTitle'] ?? $path['PathCode'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Scenario</label>
                  <select name="ScenarioCode" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <option value="">No scenario</option>
                    <?php foreach ($scenarioOptions as $option): ?>
                      <option value="<?= h((string) ($option['ScenarioCode'] ?? '')) ?>"><?= h((string) ($option['ScenarioTitle'] ?? $option['ScenarioCode'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <textarea name="Notes" class="form-control" rows="2" <?= !$managementInstalled ? 'disabled' : '' ?>></textarea>
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-sm btn-primary" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <i class="bi bi-person-check me-1"></i>Assign
                  </button>
                </div>
              </form>
            </div>
            <div class="col-xl-6">
              <form method="post" action="index.php?route=training-admin/save-session" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                  <label class="form-label">Session Code</label>
                  <input type="text" name="SessionCode" class="form-control" placeholder="TRN-001" required <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-8">
                  <label class="form-label">Title</label>
                  <input type="text" name="SessionTitle" class="form-control" required <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Instructor User ID</label>
                  <input type="number" name="InstructorUserID" class="form-control" min="1" <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Scheduled</label>
                  <input type="datetime-local" name="ScheduledAt" class="form-control" <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Status</label>
                  <select name="Status" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <?php foreach (['planned', 'running', 'completed', 'cancelled'] as $status): ?>
                      <option value="<?= h($status) ?>"><?= h(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Path</label>
                  <select name="PathCode" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <option value="">No path</option>
                    <?php foreach ($paths as $path): ?>
                      <option value="<?= h((string) ($path['PathCode'] ?? '')) ?>"><?= h((string) ($path['PathTitle'] ?? $path['PathCode'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Scenario</label>
                  <select name="ScenarioCode" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <option value="">No scenario</option>
                    <?php foreach ($scenarioOptions as $option): ?>
                      <option value="<?= h((string) ($option['ScenarioCode'] ?? '')) ?>"><?= h((string) ($option['ScenarioTitle'] ?? $option['ScenarioCode'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">Participant User IDs</label>
                  <input type="text" name="UserIDs" class="form-control" placeholder="12, 15, 18" <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-12">
                  <label class="form-label">Notes</label>
                  <textarea name="Notes" class="form-control" rows="2" <?= !$managementInstalled ? 'disabled' : '' ?>></textarea>
                </div>
                <div class="col-12 text-end">
                  <button type="submit" class="btn btn-sm btn-primary" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <i class="bi bi-calendar-check me-1"></i>Save Session
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Session List</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Session</th>
                  <th>Scope</th>
                  <th>Status</th>
                  <th class="text-end">Participants</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($sessions === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">No instructor sessions have been configured.</td></tr>
                <?php else: ?>
                  <?php foreach ($sessions as $session): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($session['SessionTitle'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($session['SessionCode'] ?? '')) ?></div>
                      </td>
                      <td><?= h((string) (($session['PathTitle'] ?? '') ?: ($session['ScenarioTitle'] ?? ''))) ?></td>
                      <td><span class="badge <?= $statusBadge((string) ($session['Status'] ?? '')) ?>"><?= h(ucfirst((string) ($session['Status'] ?? 'planned'))) ?></span></td>
                      <td class="text-end"><?= (int) ($session['ParticipantCount'] ?? 0) ?></td>
                      <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="index.php?route=training-admin/session-dashboard&amp;session_id=<?= (int) ($session['TrainingSessionID'] ?? 0) ?>">
                          <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Support Requests</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Learner</th>
                  <th>Scenario</th>
                  <th class="text-end">Step</th>
                  <th>Target</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($stuckEvents === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">No open training support requests.</td></tr>
                <?php else: ?>
                  <?php foreach ($stuckEvents as $event): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) (($event['DisplayName'] ?? '') ?: ($event['Username'] ?? ''))) ?></div>
                        <div class="small text-muted">User <?= (int) ($event['UserID'] ?? 0) ?></div>
                      </td>
                      <td>
                        <div><?= h((string) (($event['ScenarioTitle'] ?? '') ?: ($event['ScenarioCode'] ?? ''))) ?></div>
                        <div class="small text-muted"><?= h((string) ($event['Route'] ?? '')) ?></div>
                      </td>
                      <td class="text-end"><?= (int) ($event['StepNo'] ?? 0) ?></td>
                      <td><?= h((string) ($event['TargetElementID'] ?? '')) ?></td>
                      <td class="text-end text-nowrap">
                        <form method="post" action="index.php?route=training-admin/resolve-stuck" class="d-inline-flex gap-1 align-items-center">
                          <?= csrf_field() ?>
                          <input type="hidden" name="TrainingStuckEventID" value="<?= (int) ($event['TrainingStuckEventID'] ?? 0) ?>">
                          <input type="text" name="ResolutionNote" class="form-control form-control-sm" placeholder="Resolution note">
                          <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-check2-circle me-1"></i>Resolve
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Cleanup Tags</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Table</th>
                  <th>Record Key</th>
                  <th>Scenario</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($cleanupTags === []): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No training data cleanup tags have been recorded.</td></tr>
                <?php else: ?>
                  <?php foreach ($cleanupTags as $tag): ?>
                    <tr>
                      <td><?= h((string) ($tag['TableName'] ?? '')) ?></td>
                      <td><?= h((string) ($tag['RecordKey'] ?? '')) ?></td>
                      <td><?= h((string) ($tag['ScenarioCode'] ?? '')) ?></td>
                      <td><span class="badge <?= $statusBadge((string) ($tag['CleanupStatus'] ?? '')) ?>"><?= h(ucfirst((string) ($tag['CleanupStatus'] ?? 'tagged'))) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
