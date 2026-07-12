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
$workflowUserGroups = is_array($workflowUserGroups ?? null) ? $workflowUserGroups : [];
$workflowUserGroupsInstalled = !empty($workflowUserGroupsInstalled);
$catalogInstalled = (bool) ($catalogInstalled ?? false);
$managementInstalled = (bool) ($managementInstalled ?? false);
$selectedPathCode = trim((string) ($selectedPathCode ?? ''));
$selectedPath = is_array($selectedPath ?? null) ? $selectedPath : null;
$selectedPathTitle = $selectedPath !== null ? trim((string) ($selectedPath['PathTitle'] ?? '')) : '';
$selectedPathDisplay = $selectedPathCode !== ''
    ? ($selectedPathTitle !== '' ? $selectedPathTitle . ' (' . $selectedPathCode . ')' : $selectedPathCode)
    : '';
$editSessionCode = trim((string) ($_GET['edit_session_code'] ?? ''));
$assignmentScenarioOptions = $scenarioOptions;
$selectedPathScenarioCodes = '';
if ($selectedPath !== null && is_array($selectedPath['Scenarios'] ?? null)) {
    $assignmentScenarioOptions = array_values(array_filter(
        $selectedPath['Scenarios'],
        static fn(array $scenario): bool => trim((string) ($scenario['ScenarioCode'] ?? '')) !== ''
    ));
    $selectedPathScenarioCodes = implode(', ', array_map(
        static fn(array $scenario): string => (string) ($scenario['ScenarioCode'] ?? ''),
        $assignmentScenarioOptions
    ));
}
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
        <div class="mb-2">Use this screen to organise training courses, assign scenarios or courses to users, prepare instructor-led sessions, monitor learners who request help, and track cleanup evidence.</div>
        <div class="small text-muted mb-2">A Training Course is stored as a path: a repeatable learning journey made from one or more scenarios. Use assignments for individual users, and use sessions when an instructor is taking a group through the same course or scenario.</div>
        <div class="small">Run validation before structured training so missing routes, target element IDs, or samples can be corrected before learners begin.</div>
      </div>

      <?php if (!$catalogInstalled || !$managementInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <div class="fw-semibold mb-1">Training setup is incomplete</div>
          <?php if (!$catalogInstalled): ?><div>Run <code>create_training_scenario_catalog.sql</code> before maintaining training scenarios.</div><?php endif; ?>
          <?php if (!$managementInstalled): ?><div>Run <code>create_training_management_features.sql</code> before using paths, sessions, assignments, evidence, and support tracking.</div><?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="border rounded bg-light p-3 mb-4">
        <form method="get" action="index.php" class="row g-2 align-items-end">
          <input type="hidden" name="route" value="training-admin/operations">
          <div class="col-lg-5">
            <label class="form-label" for="TrainingSelectedPathCode">Selected Course / Path</label>
            <select id="TrainingSelectedPathCode" name="path_code" class="form-select" onchange="this.form.submit()" <?= !$managementInstalled ? 'disabled' : '' ?>>
              <option value="">No course/path selected</option>
              <?php foreach ($paths as $path): ?>
                <?php $pathCode = (string) ($path['PathCode'] ?? ''); ?>
                <option value="<?= h($pathCode) ?>" <?= $pathCode === $selectedPathCode ? 'selected' : '' ?>>
                  <?= h((string) ($path['PathTitle'] ?? $pathCode)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-5">
            <div class="small text-muted">Choose a course/path first. CBMS remembers this selection for Training Operations until you change or clear it.</div>
            <?php if ($selectedPath !== null): ?>
              <div class="small mt-1">
                <span class="badge text-bg-primary"><?= h((string) ($selectedPath['PathCode'] ?? '')) ?></span>
                <span class="text-muted ms-1"><?= h((string) ($selectedPath['Audience'] ?? '')) ?></span>
              </div>
            <?php endif; ?>
          </div>
          <div class="col-lg-2 text-lg-end">
            <button type="submit" class="btn btn-sm btn-outline-primary" <?= !$managementInstalled ? 'disabled' : '' ?>>
              <i class="bi bi-check2 me-1"></i>Select
            </button>
            <?php if ($selectedPathCode !== ''): ?>
              <a class="btn btn-sm btn-outline-secondary" href="index.php?route=training-admin/operations&amp;clear_path=1">Clear</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <ul class="nav nav-tabs" id="trainingOperationsTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="training-ops-courses-tab" data-bs-toggle="tab" data-bs-target="#training-ops-courses" type="button" role="tab" aria-controls="training-ops-courses" aria-selected="true">
            <i class="bi bi-diagram-3 me-1"></i>Courses / Paths
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="training-ops-assignments-tab" data-bs-toggle="tab" data-bs-target="#training-ops-assignments" type="button" role="tab" aria-controls="training-ops-assignments" aria-selected="false">
            <i class="bi bi-person-check me-1"></i>Assign Users
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="training-ops-sessions-tab" data-bs-toggle="tab" data-bs-target="#training-ops-sessions" type="button" role="tab" aria-controls="training-ops-sessions" aria-selected="false">
            <i class="bi bi-calendar-check me-1"></i>Sessions
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="training-ops-support-tab" data-bs-toggle="tab" data-bs-target="#training-ops-support" type="button" role="tab" aria-controls="training-ops-support" aria-selected="false">
            <i class="bi bi-life-preserver me-1"></i>Support
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="training-ops-cleanup-tab" data-bs-toggle="tab" data-bs-target="#training-ops-cleanup" type="button" role="tab" aria-controls="training-ops-cleanup" aria-selected="false">
            <i class="bi bi-tags me-1"></i>Cleanup
          </button>
        </li>
      </ul>

      <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white" id="trainingOperationsTabContent">
        <div class="tab-pane fade show active" id="training-ops-courses" role="tabpanel" aria-labelledby="training-ops-courses-tab" tabindex="0">
      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Training Courses / Paths</h5>
          <div class="small text-muted mt-1">Create a reusable course by grouping scenario codes into a path, then assign that course to users on the Assign Users tab.</div>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?route=training-admin/save-path" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-2">
              <label class="form-label">Course / Path Code</label>
              <input id="TrainingPathCode" type="text" name="PathCode" class="form-control" value="<?= h((string) ($selectedPath['PathCode'] ?? '')) ?>" placeholder="BASIC-USERS" required <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-3">
              <label class="form-label">Course Title</label>
              <input id="TrainingPathTitle" type="text" name="PathTitle" class="form-control" value="<?= h((string) ($selectedPath['PathTitle'] ?? '')) ?>" placeholder="Basic Users Training" required <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-2">
              <label class="form-label">Audience</label>
              <input id="TrainingPathAudience" type="text" name="Audience" class="form-control" value="<?= h((string) ($selectedPath['Audience'] ?? '')) ?>" placeholder="New users" <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-1">
              <label class="form-label">Sort</label>
              <input id="TrainingPathSortOrder" type="number" name="SortOrder" class="form-control" value="<?= h((string) ($selectedPath['SortOrder'] ?? 0)) ?>" min="0" step="10" <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Scenario Codes</label>
              <input id="TrainingPathScenarioCodes" type="text" name="ScenarioCodes" class="form-control" value="<?= h($selectedPathScenarioCodes) ?>" placeholder="USERS_CREATE_DEMO, USERS_EDIT_RECORD" <?= !$managementInstalled ? 'disabled' : '' ?>>
              <div class="form-text">Comma-separated scenario codes in the order learners should complete them.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea id="TrainingPathDescription" name="Description" class="form-control" rows="2" <?= !$managementInstalled ? 'disabled' : '' ?>><?= h((string) ($selectedPath['Description'] ?? '')) ?></textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
              <div class="form-check me-auto">
                <input id="training-path-active" type="checkbox" name="ActiveFlag" class="form-check-input" <?= (int) ($selectedPath['ActiveFlag'] ?? 1) === 1 ? 'checked' : '' ?> <?= !$managementInstalled ? 'disabled' : '' ?>>
                <label class="form-check-label" for="training-path-active">Active course/path</label>
              </div>
              <button id="training-path-save-btn" type="submit" class="btn btn-sm btn-primary" <?= !$managementInstalled ? 'disabled' : '' ?>>
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
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($paths === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">No training paths have been configured.</td></tr>
                <?php else: ?>
                  <?php foreach ($paths as $path): ?>
                    <?php $rowPathCode = (string) ($path['PathCode'] ?? ''); ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($path['PathTitle'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h($rowPathCode) ?></div>
                      </td>
                      <td><?= h((string) ($path['Audience'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($path['ScenarioCount'] ?? 0) ?></td>
                      <td><span class="badge <?= ((int) ($path['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= ((int) ($path['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span></td>
                      <td class="text-end">
                        <?php if ($rowPathCode === $selectedPathCode): ?>
                          <span class="badge text-bg-primary">Selected</span>
                        <?php else: ?>
                          <a class="btn btn-sm btn-outline-primary" href="index.php?route=training-admin/operations&amp;path_code=<?= urlencode($rowPathCode) ?>">Select</a>
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

        <div class="tab-pane fade" id="training-ops-assignments" role="tabpanel" aria-labelledby="training-ops-assignments-tab" tabindex="0">
      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Assign Training To Users</h5>
          <div class="small text-muted mt-1">Select workflow user groups or individual learners, choose the course/path or scenario scope, then create user-specific training assignments.</div>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-12 col-xxl-9">
              <form method="post" action="index.php?route=training-admin/save-assignment" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="return_path_code" value="<?= h($selectedPathCode) ?>">
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                    <div>
                      <label class="form-label mb-0" for="TrainingAssignmentWorkflowUserGroups">Workflow User Groups</label>
                      <div class="form-text mt-0">Select one or more reusable groups to assign every active member in one step.</div>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-user-groups/list">
                      <i class="bi bi-people me-1"></i>Manage Groups
                    </a>
                  </div>
                  <?php if (!$workflowUserGroupsInstalled): ?>
                    <div class="alert alert-warning py-2 mb-0">Workflow user groups are not installed. Run <code>create_workflow_user_groups.sql</code> to assign training by group.</div>
                  <?php elseif ($workflowUserGroups === []): ?>
                    <div class="alert alert-info py-2 mb-0">No active workflow user groups are available.</div>
                  <?php else: ?>
                    <select id="TrainingAssignmentWorkflowUserGroups" name="WorkflowUserGroupIDs[]" class="form-select" multiple size="5" <?= !$managementInstalled ? 'disabled' : '' ?>>
                      <?php foreach ($workflowUserGroups as $group): ?>
                        <?php
                          $groupId = (int) ($group['WorkflowUserGroupID'] ?? 0);
                          if ($groupId <= 0) {
                              continue;
                          }
                          $groupName = trim((string) ($group['GroupName'] ?? ''));
                          if ($groupName === '') {
                              $groupName = 'Workflow Group #' . $groupId;
                          }
                          $activeMemberCount = (int) ($group['ActiveMemberCount'] ?? 0);
                        ?>
                        <option value="<?= $groupId ?>"><?= h($groupName) ?> (<?= $activeMemberCount ?> active)</option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>
                <div class="col-12"
                     data-training-user-picker
                     data-user-search-url="index.php?route=training-admin/user-search"
                     data-hidden-name="AssignmentUserIDs[]"
                     data-empty-label="No users selected."
                     data-min-search-label="Type at least 2 characters to search active users.">
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                    <label class="form-label mb-0" for="TrainingAssignmentUserSearch">Users</label>
                    <span class="badge text-bg-secondary" data-training-user-selected-count>0 selected</span>
                  </div>
                  <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="TrainingAssignmentUserSearch"
                           type="search"
                           class="form-control"
                           placeholder="Search users by name, username, or email"
                           data-training-user-search
                           <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <button type="button" class="btn btn-outline-secondary" data-training-user-clear <?= !$managementInstalled ? 'disabled' : '' ?>>
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </button>
                  </div>
                  <div class="border rounded bg-white overflow-auto mb-2" style="max-height: 220px;" id="TrainingAssignmentUserSearchResults" data-training-user-results>
                    <div class="text-center text-muted small py-3" data-training-user-status>Type to search active users.</div>
                  </div>
                  <div id="TrainingAssignmentUserIDs" class="border rounded bg-light p-2" data-training-selected-users>
                    <div class="text-muted small" data-training-user-none>No users selected.</div>
                  </div>
                  <div class="form-text">Use individual users for exceptions or small ad hoc assignments. Duplicate open assignments are skipped automatically.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Selected Course / Path</label>
                  <?php if ($selectedPathCode !== ''): ?>
                    <div id="TrainingAssignmentPathCode" class="form-control bg-light"><?= h($selectedPathDisplay) ?></div>
                    <input type="hidden" name="PathCode" value="<?= h($selectedPathCode) ?>">
                    <div class="form-text">Change this using Selected Course / Path at the top of Training Operations.</div>
                  <?php else: ?>
                    <div id="TrainingAssignmentPathCode" class="alert alert-warning py-2 mb-0">No course/path selected. Choose one at the top, or select one scenario below.</div>
                    <input type="hidden" name="PathCode" value="">
                  <?php endif; ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Scenario</label>
                  <select id="TrainingAssignmentScenarioCode" name="ScenarioCode" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <?php if ($selectedPathCode !== ''): ?>
                      <option value="">All scenarios in selected course/path</option>
                    <?php else: ?>
                      <option value="">Select a scenario</option>
                    <?php endif; ?>
                    <?php foreach ($assignmentScenarioOptions as $option): ?>
                      <option value="<?= h((string) ($option['ScenarioCode'] ?? '')) ?>"><?= h((string) ($option['ScenarioTitle'] ?? $option['ScenarioCode'] ?? '')) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($selectedPathCode !== ''): ?>
                    <div class="form-text">Leave this as all scenarios to assign the full selected course/path.</div>
                  <?php endif; ?>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Due Date</label>
                  <input id="TrainingAssignmentDueDate" type="date" name="DueDate" class="form-control" <?= !$managementInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-12">
                  <label class="form-label">Assignment Notes</label>
                  <textarea id="TrainingAssignmentNotes" name="Notes" class="form-control" rows="2" <?= !$managementInstalled ? 'disabled' : '' ?>></textarea>
                </div>
                <details class="col-12">
                  <summary class="small text-muted">Advanced: enter user IDs manually</summary>
                  <div class="mt-2">
                    <label class="form-label">Manual User IDs</label>
                    <input id="TrainingAssignmentManualUserIDs" type="text" name="ManualUserIDs" class="form-control" placeholder="Optional: 12, 15, 18" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <div class="form-text">Use this only when you already know the user IDs or need a quick test assignment.</div>
                  </div>
                </details>
                <div class="col-12 text-end">
                  <button id="training-assignment-save-btn" type="submit" class="btn btn-sm btn-primary" <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <i class="bi bi-person-check me-1"></i>Assign
                  </button>
                </div>
              </form>
            </div>
            <div class="col-12 col-xxl-3">
              <div class="alert alert-light border mb-0">
                <div class="fw-semibold mb-1">What this creates</div>
                <div class="small text-muted">Assignments control what appears on each learner's Training Dashboard. Instructor-led sessions are managed separately on the Sessions tab.</div>
              </div>
            </div>
          </div>
          <hr class="my-4">
          <div>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
              <h6 class="mb-0">Open Assignments</h6>
              <?php if ($selectedPathCode !== ''): ?>
                <span class="badge text-bg-primary"><?= h($selectedPathCode) ?></span>
              <?php else: ?>
                <span class="badge text-bg-secondary">All open assignments</span>
              <?php endif; ?>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Course / Path</th>
                    <th>Scenario</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($assignments === []): ?>
                    <tr>
                      <td colspan="7" class="text-muted text-center py-3">
                        <?= $selectedPathCode !== '' ? 'No open assignments for the selected course/path.' : 'No open assignments found.' ?>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                      <?php
                        $assignmentUser = trim((string) ($assignment['DisplayName'] ?? ''));
                        if ($assignmentUser === '') {
                            $assignmentUser = trim((string) ($assignment['Username'] ?? ''));
                        }
                        if ($assignmentUser === '') {
                            $assignmentUser = 'User #' . (string) ($assignment['UserID'] ?? '');
                        }
                        $assignmentPath = trim((string) ($assignment['PathTitle'] ?? ''));
                        if ($assignmentPath === '') {
                            $assignmentPath = trim((string) ($assignment['PathCode'] ?? ''));
                        }
                        $assignmentScenario = trim((string) ($assignment['ScenarioTitle'] ?? ''));
                        if ($assignmentScenario === '') {
                            $assignmentScenario = trim((string) ($assignment['ScenarioCode'] ?? ''));
                        }
                      ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h($assignmentUser) ?></div>
                          <div class="small text-muted">ID <?= (int) ($assignment['UserID'] ?? 0) ?></div>
                        </td>
                        <td><?= h($assignmentPath !== '' ? $assignmentPath : 'No course/path') ?></td>
                        <td><?= h($assignmentScenario !== '' ? $assignmentScenario : 'All scenarios') ?></td>
                        <td><?= h((string) ($assignment['DueDate'] ?? '')) ?></td>
                        <td><span class="badge <?= $statusBadge((string) ($assignment['Status'] ?? '')) ?>"><?= h((string) ($assignment['Status'] ?? '')) ?></span></td>
                        <td><?= h((string) ($assignment['AssignedAt'] ?? '')) ?></td>
                        <td class="text-end">
                          <form method="post" action="index.php?route=training-admin/cancel-assignment" onsubmit="return confirm('Remove this training assignment from the learner dashboard?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="TrainingAssignmentID" value="<?= (int) ($assignment['TrainingAssignmentID'] ?? 0) ?>">
                            <input type="hidden" name="return_path_code" value="<?= h($selectedPathCode) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                              <i class="bi bi-x-circle me-1"></i>Remove
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
      </div>
        </div>

        <div class="tab-pane fade" id="training-ops-sessions" role="tabpanel" aria-labelledby="training-ops-sessions-tab" tabindex="0">
      <div class="card shadow-sm mb-3">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <h5 class="mb-0">Create Instructor Session</h5>
            <span class="badge text-bg-light d-none" id="TrainingSessionEditBadge">Editing existing session</span>
          </div>
          <div class="small text-muted mt-1">Use sessions for trainer-led events. Session participants are separate from learner dashboard assignments.</div>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?route=training-admin/save-session" class="row g-2 align-items-end" id="TrainingSessionForm">
            <?= csrf_field() ?>
            <input type="hidden" name="return_path_code" value="<?= h($selectedPathCode) ?>">
            <div class="col-md-4">
              <label class="form-label">Session Code</label>
              <input type="text" name="SessionCode" id="TrainingSessionCode" class="form-control" placeholder="Auto generated if blank" <?= !$managementInstalled ? 'disabled' : '' ?>>
              <div class="form-text">Leave blank to generate a unique code automatically.</div>
            </div>
            <div class="col-md-8">
              <label class="form-label">Session Title</label>
              <input type="text" name="SessionTitle" id="TrainingSessionTitle" class="form-control" required <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Scheduled Date/Time</label>
              <input type="datetime-local" name="ScheduledAt" id="TrainingSessionScheduledAt" class="form-control" <?= !$managementInstalled ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-4">
              <label class="form-label">Session Status</label>
              <select name="Status" id="TrainingSessionStatus" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                <?php foreach (['planned', 'running', 'completed', 'cancelled'] as $status): ?>
                  <option value="<?= h($status) ?>"><?= h(ucfirst($status)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Selected Course / Path</label>
              <?php if ($selectedPathCode !== ''): ?>
                <div class="form-control bg-light" id="TrainingSessionPathDisplay"><?= h($selectedPathDisplay) ?></div>
                <input type="hidden" name="PathCode" id="TrainingSessionPathCode" value="<?= h($selectedPathCode) ?>">
                <div class="form-text">Change this using Selected Course / Path at the top of Training Operations.</div>
              <?php else: ?>
                <div class="alert alert-warning py-2 mb-0" id="TrainingSessionPathDisplay">No course/path selected. The session can still be saved for a single scenario.</div>
                <input type="hidden" name="PathCode" id="TrainingSessionPathCode" value="">
              <?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label">Scenario Scope</label>
              <select name="ScenarioCode" id="TrainingSessionScenarioCode" class="form-select" <?= !$managementInstalled ? 'disabled' : '' ?>>
                <?php if ($selectedPathCode !== ''): ?>
                  <option value="">All scenarios in selected course/path</option>
                <?php else: ?>
                  <option value="">No scenario</option>
                <?php endif; ?>
                <?php foreach ($assignmentScenarioOptions as $option): ?>
                  <option value="<?= h((string) ($option['ScenarioCode'] ?? '')) ?>"><?= h((string) ($option['ScenarioTitle'] ?? $option['ScenarioCode'] ?? '')) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($selectedPathCode !== ''): ?>
                <div class="form-text">Only scenarios in the selected course/path are shown.</div>
              <?php endif; ?>
            </div>
            <div class="col-12">
              <div class="row g-3">
                <div class="col-12 col-xl-6"
                     id="TrainingSessionInstructorPicker"
                     data-training-user-picker
                     data-user-search-url="index.php?route=training-admin/user-search"
                     data-hidden-name="InstructorUserID"
                     data-single="1"
                     data-empty-label="No instructor selected."
                     data-min-search-label="Type at least 2 characters to search active users.">
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                    <label class="form-label mb-0" for="TrainingSessionInstructorSearch">Instructor</label>
                    <span class="badge text-bg-secondary" data-training-user-selected-count>0 selected</span>
                  </div>
                  <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="TrainingSessionInstructorSearch"
                           type="search"
                           class="form-control"
                           placeholder="Search instructor by name, username, or email"
                           data-training-user-search
                           <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <button type="button" class="btn btn-outline-secondary" data-training-user-clear <?= !$managementInstalled ? 'disabled' : '' ?>>
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </button>
                  </div>
                  <div class="border rounded bg-white overflow-auto mb-2" style="max-height: 180px;" data-training-user-results>
                    <div class="text-center text-muted small py-3" data-training-user-status>Type to search active users.</div>
                  </div>
                  <div class="border rounded bg-light p-2" data-training-selected-users>
                    <div class="text-muted small" data-training-user-none>No instructor selected.</div>
                  </div>
                </div>

                <div class="col-12 col-xl-6"
                     id="TrainingSessionParticipantPicker"
                     data-training-user-picker
                     data-user-search-url="index.php?route=training-admin/user-search"
                     data-hidden-name="SessionUserIDs[]"
                     data-empty-label="No participants selected."
                     data-min-search-label="Type at least 2 characters to search active users.">
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                    <label class="form-label mb-0" for="TrainingSessionParticipantSearch">Session Participants</label>
                    <span class="badge text-bg-secondary" data-training-user-selected-count>0 selected</span>
                  </div>
                  <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input id="TrainingSessionParticipantSearch"
                           type="search"
                           class="form-control"
                           placeholder="Search participants by name, username, or email"
                           data-training-user-search
                           <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <button type="button" class="btn btn-outline-secondary" data-training-user-clear <?= !$managementInstalled ? 'disabled' : '' ?>>
                      <i class="bi bi-x-circle me-1"></i>Clear
                    </button>
                  </div>
                  <div class="border rounded bg-white overflow-auto mb-2" style="max-height: 180px;" data-training-user-results>
                    <div class="text-center text-muted small py-3" data-training-user-status>Type to search active users.</div>
                  </div>
                  <div class="border rounded bg-light p-2" data-training-selected-users>
                    <div class="text-muted small" data-training-user-none>No participants selected.</div>
                  </div>
                  <div class="form-text">Optional. These are attendees for the instructor session.</div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input type="checkbox" name="CreateParticipantAssignments" value="1" id="TrainingSessionCreateParticipantAssignments" class="form-check-input" checked <?= !$managementInstalled ? 'disabled' : '' ?>>
                <label class="form-check-label" for="TrainingSessionCreateParticipantAssignments">
                  Also add selected participants to their Training Dashboard
                </label>
              </div>
              <div class="form-text">Creates learner assignments for the same course/path or scenario scope. Existing open assignments are skipped.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Session Notes</label>
              <textarea name="Notes" id="TrainingSessionNotes" class="form-control" rows="2" <?= !$managementInstalled ? 'disabled' : '' ?>></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="TrainingSessionNewBtn" <?= !$managementInstalled ? 'disabled' : '' ?>>
                <i class="bi bi-plus-circle me-1"></i>New Session
              </button>
              <button type="submit" class="btn btn-sm btn-primary" id="TrainingSessionSaveBtn" <?= !$managementInstalled ? 'disabled' : '' ?>>
                <i class="bi bi-calendar-check me-1"></i>Save Session
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div>
              <h5 class="mb-0">Session List</h5>
              <div class="small text-muted mt-1">Review instructor-led sessions and open the session dashboard for attendance, evidence, and trainer follow-up.</div>
            </div>
            <a href="index.php?route=training-admin/session-summary" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-calendar2-week me-1"></i>Session Summary
            </a>
          </div>
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
                    <?php
                      $participantUsersRaw = json_decode((string) ($session['ParticipantUsersJson'] ?? '[]'), true);
                      $participantUsers = is_array($participantUsersRaw) ? array_values(array_filter(array_map(
                          static function (array $user): array {
                              $userId = (int) ($user['id'] ?? 0);
                              return [
                                  'id' => $userId,
                                  'label' => (string) (($user['label'] ?? '') ?: ('User #' . $userId)),
                                  'username' => (string) ($user['username'] ?? ''),
                                  'email' => (string) ($user['email'] ?? ''),
                              ];
                          },
                          array_filter($participantUsersRaw, 'is_array')
                      ), static fn(array $user): bool => (int) ($user['id'] ?? 0) > 0)) : [];
                      if ($participantUsers === []) {
                          $participantIds = array_values(array_filter(array_map(
                              static fn(string $value): int => (int) trim($value),
                              explode(',', (string) ($session['ParticipantUserIDs'] ?? ''))
                          ), static fn(int $value): bool => $value > 0));
                          $participantUsers = array_map(
                              static fn(int $userId): array => ['id' => $userId, 'label' => 'User #' . $userId, 'username' => '', 'email' => ''],
                              $participantIds
                          );
                      }
                      $scheduledAt = trim((string) ($session['ScheduledAt'] ?? ''));
                      $scheduledAt = $scheduledAt !== '' ? str_replace(' ', 'T', substr($scheduledAt, 0, 16)) : '';
                      $sessionEditData = [
                          'code' => (string) ($session['SessionCode'] ?? ''),
                          'title' => (string) ($session['SessionTitle'] ?? ''),
                          'instructor' => (int) ($session['InstructorUserID'] ?? 0) > 0 ? [[
                              'id' => (int) ($session['InstructorUserID'] ?? 0),
                          'label' => (string) (($session['InstructorName'] ?? '') ?: ('User #' . (int) ($session['InstructorUserID'] ?? 0))),
                              'username' => '',
                              'email' => '',
                          ]] : [],
                          'pathCode' => (string) ($session['PathCode'] ?? ''),
                          'pathDisplay' => (string) (($session['PathTitle'] ?? '') ?: ($session['PathCode'] ?? '')),
                          'scenarioCode' => (string) ($session['ScenarioCode'] ?? ''),
                          'scheduledAt' => $scheduledAt,
                          'status' => (string) ($session['Status'] ?? 'planned'),
                          'notes' => (string) ($session['Notes'] ?? ''),
                          'participants' => $participantUsers,
                      ];
                    ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($session['SessionTitle'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($session['SessionCode'] ?? '')) ?></div>
                      </td>
                      <td><?= h((string) (($session['PathTitle'] ?? '') ?: ($session['ScenarioTitle'] ?? ''))) ?></td>
                      <td><span class="badge <?= $statusBadge((string) ($session['Status'] ?? '')) ?>"><?= h(ucfirst((string) ($session['Status'] ?? 'planned'))) ?></span></td>
                      <td class="text-end"><?= (int) ($session['ParticipantCount'] ?? 0) ?></td>
                      <td class="text-end text-nowrap">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-training-session-code="<?= h((string) ($session['SessionCode'] ?? '')) ?>"
                                data-training-session-edit="<?= h(json_encode($sessionEditData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
                          <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>
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
        </div>

        <div class="tab-pane fade" id="training-ops-support" role="tabpanel" aria-labelledby="training-ops-support-tab" tabindex="0">
      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Support Requests</h5>
          <div class="small text-muted mt-1">Review learners who clicked stuck/help during a guided scenario and record the resolution.</div>
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
        </div>

        <div class="tab-pane fade" id="training-ops-cleanup" role="tabpanel" aria-labelledby="training-ops-cleanup-tab" tabindex="0">
      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Cleanup Tags</h5>
          <div class="small text-muted mt-1">Review records tagged as training/demo data so cleanup evidence can be tracked after exercises are completed.</div>
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
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.location.hash) {
    const tabButton = document.querySelector(`[data-bs-target="${window.location.hash}"]`);
    if (tabButton && window.bootstrap?.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(tabButton).show();
    }
  }

  const text = (value) => document.createTextNode(String(value || ''));

  document.querySelectorAll('[data-training-user-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-training-user-search]');
    const clear = picker.querySelector('[data-training-user-clear]');
    const results = picker.querySelector('[data-training-user-results]');
    const selectedUsers = picker.querySelector('[data-training-selected-users]');
    const selectedNone = picker.querySelector('[data-training-user-none]');
    const count = picker.querySelector('[data-training-user-selected-count]');
    const searchUrl = picker.getAttribute('data-user-search-url') || 'index.php?route=training-admin/user-search';
    const hiddenName = picker.getAttribute('data-hidden-name') || 'UserIDs[]';
    const emptyLabel = picker.getAttribute('data-empty-label') || 'No users selected.';
    const minSearchLabel = picker.getAttribute('data-min-search-label') || 'Type at least 2 characters to search active users.';
    const single = picker.getAttribute('data-single') === '1';
    const selected = new Map();
    let timer = 0;
    let requestSeq = 0;

    const updateCount = () => {
      if (!count) {
        return;
      }
      count.textContent = `${selected.size} selected`;
    };

    const setStatus = (message) => {
      if (!results) {
        return;
      }
      results.innerHTML = '';
      const node = document.createElement('div');
      node.className = 'text-center text-muted small py-3';
      node.setAttribute('data-training-user-status', '');
      node.appendChild(text(message));
      results.appendChild(node);
    };

    const renderResultButtons = () => {
      results?.querySelectorAll('[data-training-user-add]').forEach((button) => {
        const id = Number(button.getAttribute('data-training-user-add') || 0);
        button.disabled = selected.has(id);
        button.textContent = selected.has(id) ? 'Added' : (single ? 'Select' : 'Add');
      });
    };

    const renderSelected = () => {
      if (!selectedUsers) {
        return;
      }
      selectedUsers.querySelectorAll('[data-training-selected-user]').forEach((node) => node.remove());
      selectedNone?.classList.toggle('d-none', selected.size > 0);

      selected.forEach((user, id) => {
        const wrap = document.createElement('span');
        wrap.className = 'badge text-bg-primary me-1 mb-1';
        wrap.setAttribute('data-training-selected-user', String(id));

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = hiddenName;
        hidden.value = String(id);

        const label = document.createElement('span');
        label.appendChild(text(user.label || `User #${id}`));

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-link text-white p-0 ms-2 align-baseline';
        remove.setAttribute('aria-label', `Remove ${user.label || `User #${id}`}`);
        remove.appendChild(text('x'));
        remove.addEventListener('click', () => {
          selected.delete(id);
          renderSelected();
          updateCount();
          renderResultButtons();
        });

        wrap.appendChild(hidden);
        wrap.appendChild(label);
        wrap.appendChild(remove);
        selectedUsers.appendChild(wrap);
      });
    };

    const renderResults = (items) => {
      if (!results) {
        return;
      }
      results.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        setStatus('No active users match the search.');
        return;
      }

      items.forEach((user) => {
        const id = Number(user.id || 0);
        if (id <= 0) {
          return;
        }

        const row = document.createElement('div');
        row.className = 'border-bottom px-3 py-2 d-flex justify-content-between gap-2 align-items-center';

        const main = document.createElement('div');
        main.className = 'min-w-0';
        const label = document.createElement('div');
        label.className = 'fw-semibold text-truncate';
        label.appendChild(text(user.label || `User #${id}`));
        const detail = document.createElement('div');
        detail.className = 'small text-muted text-truncate';
        detail.appendChild(text([user.username, user.email].filter(Boolean).join(' - ')));
        main.appendChild(label);
        main.appendChild(detail);

        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'btn btn-sm btn-outline-primary';
        add.setAttribute('data-training-user-add', String(id));
        add.addEventListener('click', () => {
          if (single) {
            selected.clear();
          }
          selected.set(id, user);
          renderSelected();
          updateCount();
          renderResultButtons();
        });

        row.appendChild(main);
        row.appendChild(add);
        results.appendChild(row);
      });
      renderResultButtons();
    };

    const searchUsers = async () => {
      const term = (search?.value || '').trim();
      const seq = ++requestSeq;
      if (term.length < 2) {
        setStatus(minSearchLabel);
        return;
      }

      setStatus('Searching...');
      const separator = searchUrl.includes('?') ? '&' : '?';
      const url = `${searchUrl}${separator}q=${encodeURIComponent(term)}&limit=50`;
      try {
        const response = await fetch(url, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));
        if (seq !== requestSeq) {
          return;
        }
        if (!response.ok || !payload.ok) {
          throw new Error(String(payload.error || 'User search failed.'));
        }
        renderResults(payload.items || []);
      } catch (error) {
        if (seq === requestSeq) {
          setStatus(error && error.message ? error.message : 'User search failed.');
        }
      }
    };

    search?.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(searchUsers, 250);
    });
    clear?.addEventListener('click', () => {
      selected.clear();
      if (search) {
        search.value = '';
      }
      setStatus('Type to search active users.');
      renderSelected();
      updateCount();
    });
    picker.addEventListener('training-user-picker:set', (event) => {
      selected.clear();
      const users = Array.isArray(event.detail?.users) ? event.detail.users : [];
      users.forEach((user) => {
        const id = Number(user.id || 0);
        if (id <= 0 || (single && selected.size > 0)) {
          return;
        }
        selected.set(id, {
          id,
          label: user.label || `User #${id}`,
          username: user.username || '',
          email: user.email || '',
        });
      });
      if (search) {
        search.value = '';
      }
      setStatus('Type to search active users.');
      renderSelected();
      updateCount();
      renderResultButtons();
    });
    setStatus('Type to search active users.');
    if (selectedNone) {
      selectedNone.textContent = emptyLabel;
    }
    renderSelected();
    updateCount();
  });

  const sessionForm = document.getElementById('TrainingSessionForm');
  const sessionCode = document.getElementById('TrainingSessionCode');
  const sessionTitle = document.getElementById('TrainingSessionTitle');
  const sessionScheduledAt = document.getElementById('TrainingSessionScheduledAt');
  const sessionStatus = document.getElementById('TrainingSessionStatus');
  const sessionPathDisplay = document.getElementById('TrainingSessionPathDisplay');
  const sessionPathCode = document.getElementById('TrainingSessionPathCode');
  const sessionScenarioCode = document.getElementById('TrainingSessionScenarioCode');
  const sessionNotes = document.getElementById('TrainingSessionNotes');
  const sessionEditBadge = document.getElementById('TrainingSessionEditBadge');
  const sessionNewBtn = document.getElementById('TrainingSessionNewBtn');
  const sessionSaveBtn = document.getElementById('TrainingSessionSaveBtn');
  const instructorPicker = document.getElementById('TrainingSessionInstructorPicker');
  const participantPicker = document.getElementById('TrainingSessionParticipantPicker');

  const setPickerUsers = (picker, users) => {
    picker?.dispatchEvent(new CustomEvent('training-user-picker:set', { detail: { users: users || [] } }));
  };

  const setFormValue = (field, value) => {
    if (field) {
      field.value = value || '';
    }
  };

  const setSessionEditMode = (editing) => {
    sessionCode?.toggleAttribute('readonly', editing);
    sessionEditBadge?.classList.toggle('d-none', !editing);
    sessionNewBtn?.classList.toggle('d-none', !editing);
    if (sessionSaveBtn) {
      sessionSaveBtn.innerHTML = editing
        ? '<i class="bi bi-calendar-check me-1"></i>Update Session'
        : '<i class="bi bi-calendar-check me-1"></i>Save Session';
    }
  };

  document.querySelectorAll('[data-training-session-edit]').forEach((button) => {
    button.addEventListener('click', () => {
      let session = {};
      try {
        session = JSON.parse(button.getAttribute('data-training-session-edit') || '{}');
      } catch (error) {
        session = {};
      }

      setFormValue(sessionCode, session.code);
      setFormValue(sessionTitle, session.title);
      setFormValue(sessionScheduledAt, session.scheduledAt);
      setFormValue(sessionStatus, session.status || 'planned');
      setFormValue(sessionPathCode, session.pathCode);
      if (sessionPathDisplay) {
        sessionPathDisplay.textContent = session.pathDisplay || 'No course/path selected.';
      }
      setFormValue(sessionScenarioCode, session.scenarioCode);
      setFormValue(sessionNotes, session.notes);
      setPickerUsers(instructorPicker, session.instructor || []);
      setPickerUsers(participantPicker, session.participants || []);
      setSessionEditMode(true);

      const tabButton = document.querySelector('[data-bs-target="#training-ops-sessions"]');
      if (tabButton && window.bootstrap?.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(tabButton).show();
      }
      sessionForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      sessionTitle?.focus({ preventScroll: true });
    });
  });

  sessionNewBtn?.addEventListener('click', () => {
    sessionForm?.reset();
    setFormValue(sessionPathCode, <?= json_encode($selectedPathCode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""' ?>);
    if (sessionPathDisplay) {
      sessionPathDisplay.textContent = <?= json_encode($selectedPathDisplay !== '' ? $selectedPathDisplay : 'No course/path selected. The session can still be saved for a single scenario.', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""' ?>;
    }
    setPickerUsers(instructorPicker, []);
    setPickerUsers(participantPicker, []);
    setSessionEditMode(false);
    sessionTitle?.focus();
  });

  const initialEditSessionCode = <?= json_encode($editSessionCode, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '""' ?>;
  if (initialEditSessionCode !== '') {
    const editButton = Array.from(document.querySelectorAll('[data-training-session-code]'))
      .find((button) => button.getAttribute('data-training-session-code') === initialEditSessionCode);
    editButton?.click();
  }
});
</script>
