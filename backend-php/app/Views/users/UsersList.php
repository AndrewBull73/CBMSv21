<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$csrf = h(csrf_token());
$query = (string) ($_GET['q'] ?? '');
$department = (string) ($_GET['department'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$trainingEnabled = (bool) ($trainingEnabled ?? false);
$trainingGuide = is_array($trainingGuide ?? null) ? $trainingGuide : null;
$trainingState = is_array($trainingGuide['state'] ?? null) ? $trainingGuide['state'] : null;
$trainingStep = is_array($trainingGuide['step'] ?? null) ? $trainingGuide['step'] : null;
$trainingIsCompleted = (bool) ($trainingGuide['isCompleted'] ?? false);
$trainingScenarioId = (string) ($trainingState['scenario_id'] ?? '');
$trainingRunnerHref = $trainingState !== null
    ? \App\Shared\TrainingScenarioCatalog::startRoute($trainingScenarioId)
    : 'index.php?route=training/scenarios';
$trainingStepNumber = (int) ($trainingStep['number'] ?? 0);
$trainingStepTarget = (string) ($trainingStep['target'] ?? '');
$trainingStepMode = (string) ($trainingStep['completion_mode'] ?? '');
$trainingTargetUserId = (int) ($trainingState['samples']['TargetUserID'] ?? 0);
$trainingScenarioQuery = $trainingScenarioId !== ''
    ? '&training_scenario_id=' . rawurlencode($trainingScenarioId)
    : '';
$createHref = 'index.php?route=users/edit';
if ($trainingStep !== null && !$trainingIsCompleted && (int) ($trainingStep['number'] ?? 0) === 1) {
    $createHref .= '&training_step_complete=1';
}
$createHref .= $trainingScenarioQuery;
$departments = array_values(array_filter(array_unique(array_map(
    static fn(array $u): string => trim((string) ($u['Department'] ?? '')),
    is_array($users ?? null) ? $users : []
))));
sort($departments);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-people me-2"></i><?= __t('menu_users') ?></h3>
        <div class="small text-muted mt-1">Maintain users, account status, and role access using the same shared admin pattern as the Strategy screens.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($trainingEnabled): ?>
          <a href="<?= h($trainingRunnerHref) ?>" class="btn btn-sm <?= $trainingState !== null ? 'btn-outline-info' : 'btn-outline-secondary' ?>">
            <i class="bi bi-mortarboard me-1"></i><?= $trainingState !== null ? __t('training_resume') : __t('training_scenarios_title') ?>
          </a>
        <?php endif; ?>
        <a href="index.php?route=users/exportPdf" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i><?= __t('export_pdf') ?></a>
        <a href="index.php?route=users/exportExcel&q=<?= urlencode($query) ?>&department=<?= urlencode($department) ?>&status=<?= urlencode($status) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i><?= __t('export_excel') ?></a>
        <a href="index.php?route=users/upload" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i><?= __t('upload_users') ?></a>
        <a href="<?= h($createHref) ?>" id="users-create-btn" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i><?= __t('create_user') ?></a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="users/list">
        <?php if ($trainingScenarioId !== ''): ?>
          <input type="hidden" name="training_scenario_id" value="<?= h($trainingScenarioId) ?>">
        <?php endif; ?>
        <div class="col-md-4">
          <input type="text" id="users-search-input" name="q" value="<?= h($query) ?>" class="form-control" placeholder="<?= __t('search') ?>... (username / email / display)">
        </div>
        <div class="col-md-3">
          <select name="department" class="form-select">
            <option value=""><?= __t('all_departments') ?></option>
            <?php foreach ($departments as $dept): ?>
              <option value="<?= h($dept) ?>" <?= $department === $dept ? 'selected' : '' ?>><?= h($dept) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value=""><?= __t('all_status') ?></option>
            <option value="1" <?= $status === '1' ? 'selected' : '' ?>><?= __t('enabled') ?></option>
            <option value="0" <?= $status === '0' ? 'selected' : '' ?>><?= __t('disabled') ?></option>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <?php if ($trainingStepMode === 'navigation' && $trainingStepTarget === 'users-filter-btn' && !$trainingIsCompleted && $trainingStepNumber > 0): ?>
            <input type="hidden" name="training_step_complete" value="<?= h((string) $trainingStepNumber) ?>">
          <?php endif; ?>
          <button type="submit" id="users-filter-btn" class="btn btn-outline-primary flex-fill"><?= __t('filter') ?></button>
          <a href="index.php?route=users/list<?= h($trainingScenarioQuery) ?>" class="btn btn-outline-secondary flex-fill"><?= __t('reset') ?></a>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Use filters to narrow the register before editing, unlocking, exporting, or uploading users.</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th><?= __t('user_id') ?></th>
              <th><?= __t('username_label') ?></th>
              <th><?= __t('display_name') ?></th>
              <th><?= __t('email') ?></th>
              <th><?= __t('department') ?></th>
              <th><?= __t('job_title') ?></th>
              <th><?= __t('status') ?></th>
              <th><?= __t('last_login') ?></th>
              <th><?= __t('failed_logins') ?></th>
              <th class="text-end"><?= __t('actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($users) === 0): ?>
              <tr><td colspan="10" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <?php
                $rowUserId = (int) ($u['UserID'] ?? 0);
                $editHref = 'index.php?route=users/edit&id=' . rawurlencode((string) $rowUserId) . $trainingScenarioQuery;
                $editBtnId = '';
                if ($trainingTargetUserId > 0 && $rowUserId === $trainingTargetUserId) {
                    $editBtnId = 'users-edit-target-btn';
                    if ($trainingStepMode === 'navigation' && $trainingStepTarget === 'users-edit-target-btn' && !$trainingIsCompleted && $trainingStepNumber > 0) {
                        $editHref .= '&training_step_complete=' . rawurlencode((string) $trainingStepNumber);
                    }
                }
                ?>
                <tr>
                  <td><?= h((string) ($u['UserID'] ?? '')) ?></td>
                  <td><?= h((string) ($u['Username'] ?? '')) ?></td>
                  <td><?= h((string) ($u['DisplayName'] ?? (($u['FirstName'] ?? '') . ' ' . ($u['LastName'] ?? '')))) ?></td>
                  <td><?= h((string) ($u['Email'] ?? '')) ?></td>
                  <td><?= h((string) ($u['Department'] ?? '')) ?></td>
                  <td><?= h((string) ($u['JobTitle'] ?? '')) ?></td>
                  <td>
                    <?php if ((int) ($u['IsActive'] ?? 0) === 1): ?>
                      <span class="badge text-bg-success"><?= __t('enabled') ?></span>
                    <?php else: ?>
                      <span class="badge text-bg-danger"><?= __t('disabled') ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string) ($u['LastLoginAt'] ?? '-')) ?></td>
                  <td><?= h((string) ($u['FailedLoginCount'] ?? 0)) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <a href="<?= h($editHref) ?>"<?= $editBtnId !== '' ? ' id="' . h($editBtnId) . '"' : '' ?> class="btn btn-sm btn-outline-secondary" title="<?= __t('edit_user') ?>">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#unlockModal" data-userid="<?= h((string) ($u['UserID'] ?? '')) ?>" data-username="<?= h((string) ($u['Username'] ?? '')) ?>" title="<?= __t('unlock_login') ?>">
                        <i class="bi bi-unlock"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav aria-label="User pagination" class="mt-3">
          <ul class="pagination justify-content-center">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="index.php?route=users/list&page=<?= $currentPage - 1 ?>&q=<?= urlencode($query) ?>&department=<?= urlencode($department) ?>&status=<?= urlencode($status) ?><?= h($trainingScenarioQuery) ?>">
                &laquo; <?= __t('prev') ?>
              </a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="index.php?route=users/list&page=<?= $i ?>&q=<?= urlencode($query) ?>&department=<?= urlencode($department) ?>&status=<?= urlencode($status) ?><?= h($trainingScenarioQuery) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="index.php?route=users/list&page=<?= $currentPage + 1 ?>&q=<?= urlencode($query) ?>&department=<?= urlencode($department) ?>&status=<?= urlencode($status) ?><?= h($trainingScenarioQuery) ?>">
                <?= __t('next') ?> &raquo;
              </a>
            </li>
          </ul>
          <p class="text-center text-muted small mb-0">
            <?= __t('showing') ?> <?= count($users) ?> <?= __t('of') ?> <?= $totalCount ?> <?= __t('users') ?>
          </p>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="unlockModal" tabindex="-1" aria-labelledby="unlockModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="unlockModalLabel"><?= __t('unlock_login') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __t('close') ?>"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0"><?= __t('confirm_unlock_user') ?>: <strong id="unlockUsername"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
        <form method="post" action="index.php?route=users/unlock" class="d-inline">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="UserID" id="unlockUserId" value="">
          <button type="submit" id="unlockConfirmBtn" class="btn btn-warning">
            <i class="bi bi-unlock me-1"></i><?= __t('unlock_login') ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const unlockModal = document.getElementById('unlockModal');
  if (!unlockModal) { return; }
  unlockModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    if (!button) { return; }
    const userId = button.getAttribute('data-userid') || '';
    const username = button.getAttribute('data-username') || '';
    const nameTarget = document.getElementById('unlockUsername');
    const userIdInput = document.getElementById('unlockUserId');
    if (nameTarget) { nameTarget.textContent = username; }
    if (userIdInput) { userIdInput.value = userId; }
  });
});
</script>
