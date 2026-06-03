<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php'; // CSRF helper

/** @var array|null $user */
/** @var array $roles */
/** @var array $userRoles */

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('t_or')) {
    // Translate key; if missing, fallback
    function t_or(string $key, string $fallback): string {
        $t = __t($key);
        return $t === $key ? $fallback : $t;
    }
}

$csrf     = csrf_token();
$id       = (int)($user['UserID'] ?? 0);
$titleKey = $id > 0 ? 'edit_user' : 'create_user';
$isCreateMode = $id <= 0;
$trainingEnabled = (bool) ($trainingEnabled ?? false);
$trainingGuide = is_array($trainingGuide ?? null) ? $trainingGuide : null;
$trainingState = is_array($trainingGuide['state'] ?? null) ? $trainingGuide['state'] : null;
$trainingScenarioId = (string) ($trainingState['scenario_id'] ?? '');
$trainingRunnerHref = $trainingState !== null
    ? \App\Shared\TrainingScenarioCatalog::startRoute($trainingScenarioId)
    : 'index.php?route=training/scenarios';
$trainingStep = is_array($trainingGuide['step'] ?? null) ? $trainingGuide['step'] : null;
$trainingStepNumber = (int) ($trainingStep['number'] ?? 0);
$trainingStepTarget = (string) ($trainingStep['target'] ?? '');
$trainingStepMode = (string) ($trainingStep['completion_mode'] ?? '');
$trainingScenarioQuery = $trainingScenarioId !== ''
    ? '&training_scenario_id=' . rawurlencode($trainingScenarioId)
    : '';
$navigationCompleteParam = ($trainingStepMode === 'navigation' && $trainingStepNumber > 0)
    ? '&training_step_complete=' . rawurlencode((string) $trainingStepNumber)
    : '';
$accountIframeParams = [
    'route' => 'auth/account',
    'UserID' => (string) ($user['UserID'] ?? 0),
    'iframe' => 1,
    'link_context' => 1,
    'fy' => (int) \App\Shared\SessionHelper::get('FiscalYearID', 0),
    'ver' => (int) \App\Shared\SessionHelper::get('VersionID', 0),
    'scope_dataobject_code' => (string) \App\Shared\SessionHelper::get('scope.dataobject_code', ''),
];
$accountIframeScopeName = trim((string) \App\Shared\SessionHelper::get('scope.dataobject_name', ''));
if ($accountIframeParams['scope_dataobject_code'] !== '' && $accountIframeScopeName !== '') {
    $accountIframeParams['scope_dataobject_name'] = $accountIframeScopeName;
}
$accountIframeSrc = 'index.php?' . http_build_query($accountIframeParams);

// --- Normalize assigned roles into a fast lookup set ---
$assignedRoleIds = [];
foreach (($userRoles ?? []) as $ur) {
    // accept row or scalar
    if (is_array($ur)) {
        $val = $ur['RoleID'] ?? $ur['RoleId'] ?? $ur['role_id'] ?? $ur['id'] ?? null;
    } else {
        $val = $ur;
    }
    if ($val !== null && $val !== '') {
        $assignedRoleIds[(int)$val] = true;
    }
}

$groupedRoles = [
    'Platform' => [],
    'Strategic Framework' => [],
    'Budget Submission' => [],
    'Budget Execution' => [],
    'Reporting' => [],
    'Analytics' => [],
    'Dashboards' => [],
    'Configuration' => [],
    'Administration' => [],
    'Other' => [],
];

$roleAreaFor = static function (string $roleName): string {
    $name = trim($roleName);

    return match (true) {
        $name === 'System Administrator' => 'Platform',
        str_starts_with($name, 'Strategic Framework') => 'Strategic Framework',
        str_starts_with($name, 'Budget Submission') => 'Budget Submission',
        str_starts_with($name, 'Budget Execution') => 'Budget Execution',
        str_starts_with($name, 'Reporting') => 'Reporting',
        str_starts_with($name, 'Analytics') => 'Analytics',
        str_starts_with($name, 'Dashboard') => 'Dashboards',
        str_contains($name, 'Configuration') => 'Configuration',
        $name === 'RatesEditor' => 'Budget Submission',
        default => 'Other',
    };
};

foreach (($roles ?? []) as $roleRow) {
    if (!is_array($roleRow)) {
        continue;
    }
    $roleName = (string)($roleRow['RoleName'] ?? '');
    $groupedRoles[$roleAreaFor($roleName)][] = $roleRow;
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <h3 class="mb-0"><i class="bi bi-person me-2"></i><?= __t($titleKey) ?></h3>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php if ($trainingEnabled): ?>
          <a href="<?= h($trainingRunnerHref) ?>" class="btn btn-sm <?= $trainingState !== null ? 'btn-outline-info' : 'btn-outline-secondary' ?>">
            <i class="bi bi-mortarboard me-1"></i><?= $trainingState !== null ? __t('training_resume') : __t('training_scenarios_title') ?>
          </a>
        <?php endif; ?>
        <a href="index.php?route=users/list<?= h($trainingScenarioQuery) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
        </a>
        <a href="index.php?route=users/exportUserPdf&id=<?= h((string)$id) ?>" 
           target="_blank" class="btn btn-sm btn-outline-secondary">
           <i class="bi bi-file-earmark-pdf me-1"></i> <?= __t('export_pdf') ?>
        </a>
      </div>
    </div>

    <div class="card-body">
      <div class="small text-muted mb-3">Maintain the user profile, assigned roles, and account access from one screen using the same shared admin layout as the Strategy setup pages.</div>
      <!-- Tabs -->
      <ul class="nav nav-tabs nav-tabs-sm mb-3" id="userTab" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="edit-tab" data-bs-toggle="tab"
                  data-bs-target="#edit" type="button" role="tab">
            <?= __t('edit_user') ?>
          </button>
        </li>
        <?php if ($id > 0): ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="details-tab" data-bs-toggle="tab"
                    data-bs-target="#details" type="button" role="tab">
              <?= __t('user_details') ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="roles-tab" data-bs-toggle="tab"
                    data-bs-target="#roles" type="button" role="tab">
              <?= __t('assign_roles') ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="account-tab" data-bs-toggle="tab"
                    data-bs-target="#account" type="button" role="tab">
              <?= __t('account_access') ?>
            </button>
          </li>
        <?php endif; ?>
      </ul>

      <div class="tab-content">
        <!-- Edit Tab -->
        <div class="tab-pane fade show active" id="edit" role="tabpanel">
          <form method="post" action="index.php?route=users/save<?= h($trainingScenarioQuery) ?>" class="needs-validation" novalidate>
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <?php if ($id > 0): ?>
              <input type="hidden" name="UserID" value="<?= h((string)$id) ?>">
            <?php endif; ?>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="Username" class="form-label"><?= __t('username_label') ?></label>
                <input type="text" id="Username" name="Username" class="form-control form-control-sm" required
                       value="<?= h($user['Username'] ?? '') ?>">
                <div class="invalid-feedback"><?= __t('required_field') ?: 'This field is required.' ?></div>
              </div>
              <div class="col-md-6">
                <label for="Email" class="form-label"><?= __t('email') ?></label>
                <input type="email" id="Email" name="Email" class="form-control form-control-sm"
                       value="<?= h($user['Email'] ?? '') ?>">
                <div class="invalid-feedback"><?= __t('invalid_email') ?: 'Please enter a valid email.' ?></div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="FirstName" class="form-label"><?= __t('first_name') ?></label>
                <input type="text" id="FirstName" name="FirstName" class="form-control form-control-sm"
                       value="<?= h($user['FirstName'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="LastName" class="form-label"><?= __t('last_name') ?></label>
                <input type="text" id="LastName" name="LastName" class="form-control form-control-sm"
                       value="<?= h($user['LastName'] ?? '') ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="DisplayName" class="form-label"><?= __t('display_name') ?></label>
                <input type="text" id="DisplayName" name="DisplayName" class="form-control form-control-sm"
                       data-auto-display-name="<?= $isCreateMode ? '1' : '0' ?>"
                       value="<?= h($user['DisplayName'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="Phone" class="form-label"><?= __t('phone') ?></label>
                <input type="text" id="Phone" name="Phone" class="form-control form-control-sm"
                       value="<?= h($user['Phone'] ?? '') ?>">
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="Department" class="form-label"><?= __t('department') ?></label>
                <input type="text" id="Department" name="Department" class="form-control form-control-sm"
                       value="<?= h($user['Department'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label for="JobTitle" class="form-label"><?= __t('job_title') ?></label>
                <input type="text" id="JobTitle" name="JobTitle" class="form-control form-control-sm"
                       value="<?= h($user['JobTitle'] ?? '') ?>">
              </div>
            </div>

            <div class="row g-3 mb-3" id="users-account-flags">
              <div class="col-md-3 form-check">
                <input type="checkbox" class="form-check-input" id="IsActive"
                       name="IsActive" value="1" <?= !empty($user['IsActive']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="IsActive"><?= __t('enabled') ?></label>
              </div>
              <div class="col-md-3 form-check">
                <input type="checkbox" class="form-check-input" id="ForcePasswordReset"
                       name="ForcePasswordReset" value="1" <?= !empty($user['ForcePasswordReset']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ForcePasswordReset"><?= __t('force_password_reset') ?></label>
              </div>
              <div class="col-md-3 form-check">
                <input type="checkbox" class="form-check-input" id="MustChangePassword"
                       name="MustChangePassword" value="1" <?= !empty($user['MustChangePassword']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="MustChangePassword"><?= __t('must_change_password') ?></label>
              </div>
            </div>

            <div class="mb-3">
              <label for="Notes" class="form-label"><?= __t('notes') ?></label>
              <textarea id="Notes" name="Notes" class="form-control form-control-sm" rows="3"><?= h($user['Notes'] ?? '') ?></textarea>
            </div>

            <!-- Bottom bar (Edit tab) -->
            <hr class="mt-4 mb-2">
            <div class="d-flex justify-content-between align-items-center">
              <p class="text-muted small mb-0">
                  <i class="bi bi-info-circle me-1"></i>
                <?= t_or('form_save_hint', 'Changes are not saved until you click Save.') ?>
              </p>
              <div class="d-flex gap-2">
                <a href="index.php?route=users/list<?= h($trainingScenarioQuery) ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
                </a>
                <button type="submit" id="users-save-btn" class="btn btn-sm btn-primary">
                  <i class="bi bi-save me-1"></i><?= __t('save') ?>
                </button>
              </div>
            </div>
          </form>
        </div>

        <?php if ($id > 0): ?>
        <!-- Details Tab -->
        <div class="tab-pane fade" id="details" role="tabpanel">
          <div class="table-responsive mt-3" id="users-details-review">
            <table class="table table-striped table-hover table-admin table-sm align-middle">
              <tbody>
                <tr><th><?= __t('user_id') ?></th><td><?= h((string)$user['UserID']) ?></td></tr>
                <tr><th><?= __t('last_login') ?></th><td><?= h($user['LastLoginAt'] ?? '—') ?></td></tr>
                <tr><th><?= __t('last_login_ip') ?></th><td><?= h($user['LastLoginIP'] ?? '—') ?></td></tr>
                <tr><th><?= __t('login_count') ?></th><td><?= h((string)($user['LoginCount'] ?? 0)) ?></td></tr>
                <tr><th><?= __t('failed_logins') ?></th><td><?= h((string)($user['FailedLoginCount'] ?? 0)) ?></td></tr>
                <tr><th><?= __t('last_failed_login') ?></th><td><?= h($user['LastFailedLoginAt'] ?? '—') ?></td></tr>
                <tr><th><?= __t('created_at') ?></th><td><?= h($user['CreatedAt'] ?? '—') ?></td></tr>
                <tr><th><?= __t('created_by') ?></th><td><?= h((string)($user['CreatedBy'] ?? '—')) ?></td></tr>
                <tr><th><?= __t('updated_at') ?></th><td><?= h($user['UpdatedAt'] ?? '—') ?></td></tr>
                <tr><th><?= __t('updated_by') ?></th><td><?= h((string)($user['UpdatedBy'] ?? '—')) ?></td></tr>
              </tbody>
            </table>
          </div>

          <!-- Bottom bar (Details tab) -->
          <hr class="mt-4 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <p class="text-muted small mb-0">
              <i class="bi bi-info-circle me-1"></i>
              <?= t_or('form_save_hint', 'Changes are not saved until you click Save.') ?>
            </p>
            <div class="d-flex gap-2">
              <a href="index.php?route=users/list<?= h($trainingScenarioQuery) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
              </a>
            </div>
          </div>
        </div>

        <!-- Roles Tab -->
        <div class="tab-pane fade" id="roles" role="tabpanel">
          <form method="post" action="index.php?route=users/saveRoles<?= h($trainingScenarioQuery) ?>" class="mt-3 needs-validation" novalidate id="users-roles-review">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="UserID" value="<?= h((string)$user['UserID']) ?>">

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0"><?= __t('assign_roles') ?></label>
                <span class="text-muted small">Roles are grouped by functional area.</span>
              </div>
              <?php if (!empty($roles)): ?>
                <div class="row g-3">
                  <?php foreach ($groupedRoles as $areaLabel => $areaRoles): ?>
                    <?php if (empty($areaRoles)) continue; ?>
                    <div class="col-md-6 col-xl-4">
                      <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                          <strong class="small"><?= h($areaLabel) ?></strong>
                          <span class="badge text-bg-light border"><?= count($areaRoles) ?></span>
                        </div>
                        <div class="card-body py-2">
                          <?php foreach ($areaRoles as $r): ?>
                            <?php $rid = (int)($r['RoleID'] ?? 0); ?>
                            <?php if ($rid === 0) continue; ?>
                            <div class="form-check mb-2">
                              <input class="form-check-input"
                                     type="checkbox"
                                     name="RoleIDs[]"
                                     value="<?= $rid ?>"
                                     id="role_<?= $rid ?>"
                                     <?= isset($assignedRoleIds[$rid]) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="role_<?= $rid ?>">
                                <?= h((string)($r['RoleName'] ?? 'Unknown Role')) ?>
                              </label>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted mb-0">  <i class="bi bi-info-circle me-1"></i><?= __t('no_roles_found') ?: 'No roles found.' ?></p>
              <?php endif; ?>
            </div>

            <!-- Bottom bar (Roles tab) -->
            <hr class="mt-4 mb-2">
            <div class="d-flex justify-content-between align-items-center">
              <p class="text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i>
                <?= t_or('form_save_hint', 'Changes are not saved until you click Save.') ?>
              </p>
              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                  <i class="bi bi-save me-1"></i><?= __t('save_roles') ?: __t('save') ?>
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Account & Access Tab -->
        <div class="tab-pane fade" id="account" role="tabpanel">
          <div class="mt-3" id="users-account-review">
            <iframe src="<?= h($accountIframeSrc) ?>"
                    style="width:100%;height:600px;border:0;"
                    title="<?= __t('account_access') ?>">
            </iframe>
          </div>

          <!-- Bottom bar (Account tab) -->
          <hr class="mt-4 mb-2">
          <div class="d-flex justify-content-between align-items-center">    
            <p class="text-muted small mb-0">   
            <i class="bi bi-info-circle me-1"></i>
              <?= t_or('form_save_hint', 'Changes are not saved until you click Save.') ?>
            </p>
            <div class="d-flex gap-2">
              <?php
              $accountBackHref = 'index.php?route=users/list' . $trainingScenarioQuery;
              if ($trainingStepTarget === 'users-account-back-btn' && $navigationCompleteParam !== '') {
                  $accountBackHref .= $navigationCompleteParam;
              }
              ?>
              <a href="<?= h($accountBackHref) ?>" id="users-account-back-btn" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Bootstrap validation (consistent with other forms)
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

<script>
// Deep-link to a specific tab via hash
document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash) {
    const triggerEl = document.querySelector(`button[data-bs-target="${hash}"]`);
    if (triggerEl) new bootstrap.Tab(triggerEl).show();
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const displayName = document.getElementById('DisplayName');
  const firstName = document.getElementById('FirstName');
  const lastName = document.getElementById('LastName');
  if (!displayName || !firstName || !lastName || displayName.dataset.autoDisplayName !== '1') {
    return;
  }

  let displayNameTouched = String(displayName.value || '').trim() !== '';

  const buildDisplayName = () => {
    return [String(firstName.value || '').trim(), String(lastName.value || '').trim()]
      .filter(Boolean)
      .join(' ');
  };

  const syncDisplayName = () => {
    if (displayNameTouched) {
      return;
    }
    displayName.value = buildDisplayName();
  };

  displayName.addEventListener('input', () => {
    displayNameTouched = true;
  });

  displayName.addEventListener('blur', () => {
    if (String(displayName.value || '').trim() === '') {
      displayNameTouched = false;
      syncDisplayName();
    }
  });

  firstName.addEventListener('input', syncDisplayName);
  lastName.addEventListener('input', syncDisplayName);
  syncDisplayName();
});
</script>
