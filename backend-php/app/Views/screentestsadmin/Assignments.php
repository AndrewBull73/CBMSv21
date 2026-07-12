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
$scenarioRows = is_array($scenarioRows ?? null) ? $scenarioRows : [];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$assignments = is_array($assignments ?? null) ? $assignments : [];
$workflowUserGroups = is_array($workflowUserGroups ?? null) ? $workflowUserGroups : [];
$workflowUserGroupsInstalled = (bool) ($workflowUserGroupsInstalled ?? false);

$scenariosByModule = [];
foreach ($scenarioRows as $scenario) {
    $moduleName = trim((string) ($scenario['module'] ?? ''));
    if ($moduleName === '') {
        $moduleName = 'Uncategorised';
    }
    $scenariosByModule[$moduleName][] = $scenario;
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-person-check me-2"></i>Assign Test Scripts</h3>
        <div class="small text-muted mt-1">Assign specific scripts or a whole module group to users for structured testing.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=screen-tests/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-circle me-1"></i>Tester View</a>
        <a href="index.php?route=screen-tests-admin/summary" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart-line me-1"></i>Summary</a>
        <a href="index.php?route=screen-tests-admin/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Catalogue</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php
      $testingQuickLinksMode = 'admin';
      require __DIR__ . '/../screentests/_TestingQuickLinks.php';
      $testingHelperTitle = 'How to assign testing work';
      $testingHelperItems = [
          'Select one or more users, workflow user groups, or both to define the tester audience.',
          'Assign a whole <strong>Module Group</strong>, choose specific scripts, or combine both options for targeted coverage.',
          'Use due dates and notes to tell testers what cycle, deadline, or special data context applies.',
      ];
      require __DIR__ . '/../screentests/_TestingHelperInstructions.php';
      ?>

      <?php if (!$assignmentsInstalled): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1">Assignment storage is not installed</div>
          <div class="small">Run <code><?= h($createAssignmentsScript) ?></code> before assigning test scripts to users.</div>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=screen-tests-admin/save-assignment" class="row g-3 mb-4">
        <?= csrf_field() ?>
        <div class="col-lg-5"
             data-training-user-picker
             data-user-search-url="index.php?route=screen-tests-admin/user-search"
             data-hidden-name="AssignmentUserIDs[]"
             data-empty-label="No users selected."
             data-min-search-label="Type at least 2 characters to search active users.">
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
            <label class="form-label mb-0" for="ScreenTestAssignmentUserSearch">Users</label>
            <span class="badge text-bg-secondary" data-training-user-selected-count>0 selected</span>
          </div>
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="ScreenTestAssignmentUserSearch"
                   type="search"
                   class="form-control"
                   placeholder="Search users by name, username, or email"
                   data-training-user-search
                   <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
            <button type="button" class="btn btn-outline-secondary" data-training-user-clear <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
              <i class="bi bi-x-circle me-1"></i>Clear
            </button>
          </div>
          <div class="border rounded bg-white overflow-auto mb-2" style="max-height: 220px;" data-training-user-results>
            <div class="text-center text-muted small py-3" data-training-user-status>Type to search active users.</div>
          </div>
          <div id="ScreenTestAssignmentUserIDs" class="border rounded bg-light p-2" data-training-selected-users>
            <div class="text-muted small" data-training-user-none>No users selected.</div>
          </div>
          <div class="form-text">Use individual users for exceptions or small ad hoc assignments. Duplicate open assignments are skipped automatically.</div>
        </div>

        <div class="col-lg-3">
          <label class="form-label" for="screen-test-workflow-groups">Workflow User Groups</label>
          <select id="screen-test-workflow-groups" name="WorkflowUserGroupIDs[]" class="form-select" multiple size="5" <?= (!$assignmentsInstalled || !$workflowUserGroupsInstalled) ? 'disabled' : '' ?>>
            <?php foreach ($workflowUserGroups as $group): ?>
              <?php $groupId = (int) ($group['WorkflowUserGroupID'] ?? 0); ?>
              <option value="<?= h((string) $groupId) ?>">
                <?= h((string) ($group['GroupName'] ?? ('Group #' . $groupId))) ?> (<?= h((string) (int) ($group['ActiveMemberCount'] ?? $group['MemberCount'] ?? 0)) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (!$workflowUserGroupsInstalled): ?>
            <div class="form-text">Workflow user groups are not installed.</div>
          <?php endif; ?>
        </div>

        <div class="col-lg-2">
          <label class="form-label" for="screen-test-module">Module Group</label>
          <select id="screen-test-module" name="ModuleName" class="form-select" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
            <option value="">No module group</option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>"><?= h((string) $moduleOption) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Assigns every script in the selected module.</div>
        </div>

        <div class="col-lg-2">
          <label class="form-label" for="screen-test-due-date">Due Date</label>
          <input type="date" id="screen-test-due-date" name="DueDate" class="form-control" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
        </div>

        <div class="col-12">
          <label class="form-label">Specific Test Scripts</label>
          <div class="border rounded p-3 bg-light" style="max-height: 320px; overflow: auto;">
            <?php foreach ($scenariosByModule as $moduleName => $moduleScenarios): ?>
              <div class="fw-semibold mb-2"><?= h($moduleName) ?></div>
              <div class="row g-2 mb-3">
                <?php foreach ($moduleScenarios as $scenario): ?>
                  <?php $scenarioId = (string) ($scenario['id'] ?? ''); ?>
                  <div class="col-md-6 col-xl-4">
                    <label class="form-check border rounded bg-white px-3 py-2 h-100">
                      <input class="form-check-input me-2" type="checkbox" name="ScenarioCodes[]" value="<?= h($scenarioId) ?>" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
                      <span class="form-check-label">
                        <span class="fw-semibold"><?= h((string) ($scenario['title'] ?? $scenarioId)) ?></span>
                        <span class="d-block small text-muted"><?= h($scenarioId) ?></span>
                      </span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label" for="screen-test-assignment-notes">Notes</label>
          <textarea id="screen-test-assignment-notes" name="Notes" rows="2" class="form-control" <?= !$assignmentsInstalled ? 'disabled' : '' ?>></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button type="submit" class="btn btn-primary" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
            <i class="bi bi-person-check me-1"></i>Assign Test Scripts
          </button>
        </div>
      </form>

      <div class="border rounded p-3 mb-4 bg-light">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <div>
            <h4 class="h6 mb-1"><i class="bi bi-archive me-1"></i>Assignment Cleanup</h4>
            <div class="small text-muted">Archive old assignment rows so active assignment lists stay focused.</div>
          </div>
        </div>
        <form method="post" action="index.php?route=screen-tests-admin/cleanup-assignments" class="row g-2 align-items-end" onsubmit="return confirm('Clean up matching test script assignments? This will archive matching assignment rows from active testing lists.');">
          <?= csrf_field() ?>
          <div class="col-lg-4">
            <label class="form-label" for="screen-test-cleanup-scope">Cleanup Scope</label>
            <select id="screen-test-cleanup-scope" name="CleanupScope" class="form-select" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
              <option value="completed">Completed assignments</option>
              <option value="closed">Completed or cancelled assignments</option>
              <option value="overdue_open">Overdue open assignments</option>
            </select>
          </div>
          <div class="col-lg-3">
            <label class="form-label" for="screen-test-cleanup-module">Module</label>
            <select id="screen-test-cleanup-module" name="CleanupModuleName" class="form-select" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
              <option value="">All modules</option>
              <?php foreach ($moduleOptions as $moduleOption): ?>
                <option value="<?= h((string) $moduleOption) ?>"><?= h((string) $moduleOption) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-lg-3">
            <label class="form-label" for="screen-test-cleanup-due-before">Due On Or Before</label>
            <input type="date" id="screen-test-cleanup-due-before" name="CleanupDueBefore" class="form-control" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
            <div class="form-text">Leave blank to ignore due date.</div>
          </div>
          <div class="col-lg-2 d-grid">
            <button type="submit" class="btn btn-outline-danger" <?= !$assignmentsInstalled ? 'disabled' : '' ?>>
              <i class="bi bi-archive me-1"></i>Clean Up
            </button>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Script</th>
              <th>Module</th>
              <th>Due</th>
              <th>Status</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($assignments === []): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No open test script assignments.</td></tr>
            <?php else: ?>
              <?php foreach ($assignments as $assignment): ?>
                <?php
                $assignmentId = (int) ($assignment['ScreenTestAssignmentID'] ?? 0);
                $userName = trim((string) ($assignment['DisplayName'] ?? ''));
                if ($userName === '') {
                    $userName = trim((string) ($assignment['Username'] ?? 'User #' . (int) ($assignment['UserID'] ?? 0)));
                }
                $status = strtolower(trim((string) ($assignment['Status'] ?? 'assigned')));
                $statusClass = match ($status) {
                    'completed' => 'text-bg-success',
                    'in_progress' => 'text-bg-warning',
                    'cancelled' => 'text-bg-dark',
                    default => 'text-bg-primary',
                };
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($userName) ?></div>
                    <div class="small text-muted"><?= h((string) ($assignment['Username'] ?? '')) ?></div>
                  </td>
                  <td><code><?= h((string) ($assignment['ScenarioCode'] ?? '')) ?></code></td>
                  <td><?= h((string) ($assignment['ModuleName'] ?? '')) ?></td>
                  <td><?= h((string) ($assignment['DueDate'] ?? '')) ?></td>
                  <td><span class="badge <?= h($statusClass) ?>"><?= h($status) ?></span></td>
                  <td class="text-end">
                    <form method="post" action="index.php?route=screen-tests-admin/cancel-assignment" onsubmit="return confirm('Remove this test script assignment?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="ScreenTestAssignmentID" value="<?= h((string) $assignmentId) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-x-circle"></i>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const text = (value) => document.createTextNode(String(value || ''));

  document.querySelectorAll('[data-training-user-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-training-user-search]');
    const clear = picker.querySelector('[data-training-user-clear]');
    const results = picker.querySelector('[data-training-user-results]');
    const selectedUsers = picker.querySelector('[data-training-selected-users]');
    const selectedNone = picker.querySelector('[data-training-user-none]');
    const count = picker.querySelector('[data-training-user-selected-count]');
    const searchUrl = picker.getAttribute('data-user-search-url') || 'index.php?route=screen-tests-admin/user-search';
    const hiddenName = picker.getAttribute('data-hidden-name') || 'AssignmentUserIDs[]';
    const emptyLabel = picker.getAttribute('data-empty-label') || 'No users selected.';
    const minSearchLabel = picker.getAttribute('data-min-search-label') || 'Type at least 2 characters to search active users.';
    const single = picker.getAttribute('data-single') === '1';
    const selected = new Map();
    let timer = 0;
    let requestSeq = 0;

    const updateCount = () => {
      if (count) {
        count.textContent = `${selected.size} selected`;
      }
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
    search?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchUsers();
      }
    });
    clear?.addEventListener('click', () => {
      selected.clear();
      if (search) {
        search.value = '';
      }
      setStatus('Type to search active users.');
      renderSelected();
      updateCount();
      renderResultButtons();
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
});
</script>
