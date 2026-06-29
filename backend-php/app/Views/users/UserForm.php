<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php'; // CSRF helper

/** @var array|null $user */
/** @var array $roles */
/** @var array $userRoles */

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
$csrf     = csrf_token();
$id       = (int)($user['UserID'] ?? 0);
$titleKey = $id > 0 ? 'edit_user' : 'create_user';
$isCreateMode = $id <= 0;
$flash = is_array($flash ?? null) ? $flash : null;
$accessReadiness = is_array($accessReadiness ?? null) ? $accessReadiness : null;
$dataObjectAccess = is_array($dataObjectAccess ?? null) ? $dataObjectAccess : [];
$dataObjectDirectAccess = is_array($dataObjectDirectAccess ?? null) ? $dataObjectDirectAccess : [];
$dataObjectAccessError = trim((string)($dataObjectAccessError ?? ''));
$dataObjectAccessFiscalYear = (int)($dataObjectAccessFiscalYear ?? 0);
$canManageDataObjectAccess = (bool)($canManageDataObjectAccess ?? false);
$dataObjectEffectiveCount = count($dataObjectAccess);
$dataObjectDirectCount = count($dataObjectDirectAccess);
$dataObjectInheritedCount = max(0, $dataObjectEffectiveCount - $dataObjectDirectCount);
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

$dataObjectAccessReportHref = 'index.php?route=dataobjectcodes/access_report&user=' . rawurlencode((string)$id);
$dataObjectGrantHref = 'index.php?route=dataobjectcodes/access_form';
if ($id > 0) {
    $dataObjectGrantHref .= '&user=' . rawurlencode((string)$id) . '&return_user=' . rawurlencode((string)$id);
}
$dataObjectAccessManagementHref = 'index.php?route=dataobjectcodes/access';
$formatAccessLevel = static function (string $level): string {
    return match (strtolower(trim($level))) {
        'read' => 'Read',
        'edit' => 'Edit',
        'full' => 'Full',
        'delete' => 'Delete',
        default => $level !== '' ? ucfirst($level) : 'Unknown',
    };
};
$accessLevelBadgeClass = static function (string $level): string {
    return match (strtolower(trim($level))) {
        'read' => 'text-bg-secondary',
        'edit' => 'text-bg-primary',
        'full' => 'text-bg-success',
        'delete' => 'text-bg-danger',
        default => 'text-bg-light border',
    };
};

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
    'Administration' => [],
    'System Configuration' => [],
    'Organisation & Chart of Accounts' => [],
    'Budget Strategy' => [],
    'Budget Planning' => [],
    'Budget Submission' => [],
    'Budget Execution' => [],
    'Workflow Operations' => [],
    'Reports & Analysis' => [],
    'Other' => [],
];

$roleAreaFor = static function (string $roleName): string {
    $name = trim($roleName);

    return match (true) {
        in_array($name, ['Super Admin', 'System Administrator', 'Security Administrator'], true) => 'Administration',
        in_array($name, ['Configuration Administrator', 'Financial Configuration Administrator', 'Strategy Configuration Administrator'], true) => 'System Configuration',
        in_array($name, ['Base Configuration Administrator', 'Organisation / COA Administrator'], true) => 'Organisation & Chart of Accounts',
        str_starts_with($name, 'Strategic Framework') => 'Budget Strategy',
        str_starts_with($name, 'Budget Strategy') => 'Budget Strategy',
        str_starts_with($name, 'Budget Planning') => 'Budget Planning',
        str_starts_with($name, 'Budget Submission') => 'Budget Submission',
        str_starts_with($name, 'Budget Execution') => 'Budget Execution',
        str_starts_with($name, 'Workflow Operations') => 'Workflow Operations',
        str_starts_with($name, 'Reporting') => 'Reports & Analysis',
        str_starts_with($name, 'Analytics') => 'Reports & Analysis',
        str_starts_with($name, 'Dashboard') => 'Reports & Analysis',
        $name === 'RatesEditor' => 'Budget Planning',
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

$accessReadinessReady = (bool)($accessReadiness['ready'] ?? false);
$accessReadinessIssues = is_array($accessReadiness['issues'] ?? null) ? $accessReadiness['issues'] : [];
$accessReadinessRoleCount = (int)($accessReadiness['role_count'] ?? count($assignedRoleIds));
$accessReadinessEffectiveDataCount = (int)($accessReadiness['effective_data_access_count'] ?? $dataObjectEffectiveCount);
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
      <?php if ($flash !== null): ?>
        <div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show app-flash" role="alert">
          <?= h((string)($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= h(__t('close')) ?>"></button>
        </div>
      <?php endif; ?>

      <?php if ($id > 0): ?>
        <div class="alert alert-<?= $accessReadinessReady ? 'success' : 'warning' ?> border shadow-sm mb-3" id="users-access-readiness">
          <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
              <div class="fw-semibold mb-1">
                <i class="bi <?= $accessReadinessReady ? 'bi-shield-check' : 'bi-shield-exclamation' ?> me-1"></i>
                Access Readiness
              </div>
              <div class="small">
                <?= $accessReadinessReady
                    ? 'This user has roles and effective Data Object Code access for the current fiscal year.'
                    : 'Complete access setup before expecting this user to work in CBMSv21.' ?>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="badge <?= $accessReadinessRoleCount > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                Roles: <?= h((string)$accessReadinessRoleCount) ?>
              </span>
              <span class="badge <?= $accessReadinessEffectiveDataCount > 0 ? 'text-bg-success' : 'text-bg-warning' ?>">
                Data Access: <?= h((string)$accessReadinessEffectiveDataCount) ?>
              </span>
            </div>
          </div>
          <?php if (!$accessReadinessReady && !empty($accessReadinessIssues)): ?>
            <div class="mt-2 small">
              <?= h(implode(' ', array_map('strval', $accessReadinessIssues))) ?>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3">
              <a href="#roles" class="btn btn-sm btn-outline-secondary" data-jump-tab="#roles">
                <i class="bi bi-people me-1"></i>Assign Roles
              </a>
              <?php if ($canManageDataObjectAccess): ?>
                <a href="<?= h($dataObjectGrantHref) ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-person-plus me-1"></i>Grant Data Access
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

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
            <button class="nav-link" id="data-object-access-tab" data-bs-toggle="tab"
                    data-bs-target="#data-object-access" type="button" role="tab">
              <?= __t('data_object_access') ?>
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

            <?php if ($isCreateMode): ?>
              <div class="card shadow-sm mb-3" id="users-onboarding-card">
                <div class="card-header">
                  <h5 class="mb-0">Onboarding</h5>
                </div>
                <div class="card-body">
                  <div class="small text-muted mb-3">
                    Choose how the user will receive first access to CBMSv21. Email invite is recommended because no password is shared manually.
                  </div>
                  <div class="row g-3">
                    <div class="col-lg-6">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="OnboardingMode" id="OnboardingEmailInvite" value="email_invite" checked>
                        <label class="form-check-label fw-semibold" for="OnboardingEmailInvite">Send welcome invite email</label>
                      </div>
                      <div class="small text-muted ms-4">
                        Queues the <code>USER_WELCOME_INVITE</code> email template with a one-time secure login link.
                        The user sets their password when they first sign in.
                      </div>
                      <div class="ms-4 mt-2">
                        <a href="index.php?route=email-templates/list" class="btn btn-sm btn-outline-secondary">
                          <i class="bi bi-envelope-paper me-1"></i>Email Templates
                        </a>
                      </div>
                    </div>
                    <div class="col-lg-6">
                      <div class="form-check">
                        <input class="form-check-input" type="radio" name="OnboardingMode" id="OnboardingTemporaryPassword" value="temporary_password">
                        <label class="form-check-label fw-semibold" for="OnboardingTemporaryPassword">Set temporary password manually</label>
                      </div>
                      <div class="small text-muted ms-4">
                        Use only when email delivery is not ready. The user must change the password on first login.
                      </div>
                      <div class="row g-2 ms-3 mt-2 d-none" id="TemporaryPasswordFields">
                        <div class="col-md-6">
                          <label for="InitialPassword" class="form-label">Initial Password</label>
                          <input type="password" id="InitialPassword" name="InitialPassword" class="form-control form-control-sm" autocomplete="new-password" minlength="10">
                        </div>
                        <div class="col-md-6">
                          <label for="InitialPasswordConfirm" class="form-label">Confirm Password</label>
                          <input type="password" id="InitialPasswordConfirm" name="InitialPasswordConfirm" class="form-control form-control-sm" autocomplete="new-password" minlength="10">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="row g-3 mb-3" id="users-account-flags">
              <div class="col-md-3 form-check">
                <input type="checkbox" class="form-check-input" id="IsActive"
                       name="IsActive" value="1" <?= ($isCreateMode || !empty($user['IsActive'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="IsActive"><?= __t('enabled') ?></label>
              </div>
              <div class="col-md-3 form-check">
                <input type="checkbox" class="form-check-input" id="ForcePasswordReset"
                       name="ForcePasswordReset" value="1" <?= ($isCreateMode || !empty($user['ForcePasswordReset'])) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ForcePasswordReset"><?= __t('force_password_reset') ?></label>
              </div>
              <div class="col-md-3 form-check">
                <input type="checkbox" class="form-check-input" id="MustChangePassword"
                       name="MustChangePassword" value="1" <?= ($isCreateMode || !empty($user['MustChangePassword'])) ? 'checked' : '' ?>>
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
                <?= __t('form_save_hint') ?>
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
              <?= __t('form_save_hint') ?>
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
                <span class="text-muted small">Roles are grouped by menu and security area.</span>
              </div>
              <?php if (!empty($roles)): ?>
                <div class="row g-3">
                  <?php foreach ($groupedRoles as $areaLabel => $areaRoles): ?>
                    <?php if (empty($areaRoles)) continue; ?>
                    <div class="col-md-6 col-xl-4">
                      <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                          <strong class="small"><?= h($areaLabel) ?></strong>
                          <span class="badge text-bg-light border"><?= count($areaRoles) ?></span>
                        </div>
                        <div class="card-body">
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
                <?= __t('form_save_hint') ?>
              </p>
              <div class="d-flex gap-2">
                <button type="submit" id="users-save-roles-btn" class="btn btn-sm btn-primary">
                  <i class="bi bi-save me-1"></i><?= __t('save_roles') ?: __t('save') ?>
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Data Object Access Tab -->
        <div class="tab-pane fade" id="data-object-access" role="tabpanel">
          <div class="mt-3" id="users-data-object-access-review">
            <div class="small text-muted mb-3">
              Current context:
              <strong>FY <?= h((string)$dataObjectAccessFiscalYear) ?></strong>
            </div>

            <div class="row g-3 mb-4">
              <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                  <div class="card-body">
                    <div class="text-muted small">Direct Grants</div>
                    <div class="fs-4 fw-semibold"><?= h((string)$dataObjectDirectCount) ?></div>
                  </div>
                </div>
              </div>
              <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                  <div class="card-body">
                    <div class="text-muted small">Inherited Access</div>
                    <div class="fs-4 fw-semibold"><?= h((string)$dataObjectInheritedCount) ?></div>
                  </div>
                </div>
              </div>
              <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                  <div class="card-body">
                    <div class="text-muted small">Effective Access</div>
                    <div class="fs-4 fw-semibold"><?= h((string)$dataObjectEffectiveCount) ?></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="alert alert-info border-0 shadow-sm mb-4">
              <div class="fw-semibold mb-1">Data Object Access</div>
              <div class="mb-2">Review the Data Object Codes this user can access for the current fiscal year.</div>
              <div class="small text-muted">Direct grants are assigned explicitly. Inherited access comes from direct grants where child codes are included.</div>
            </div>

            <?php if ($dataObjectAccessError !== ''): ?>
              <div class="alert alert-warning border-0 shadow-sm mb-4">
                <div class="fw-semibold mb-1">Access data could not be fully loaded</div>
                <div class="small"><?= h($dataObjectAccessError) ?></div>
              </div>
            <?php elseif ($dataObjectAccessFiscalYear <= 0): ?>
              <div class="alert alert-warning border-0 shadow-sm mb-4">
                <div class="fw-semibold mb-1">No fiscal year selected</div>
                <div class="small">Select a fiscal year before reviewing Data Object Code access.</div>
              </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-4">
              <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <h5 class="mb-0">Direct Grants</h5>
                <div class="d-flex gap-2 flex-wrap">
                  <?php if ($canManageDataObjectAccess): ?>
                    <a href="<?= h($dataObjectAccessReportHref) ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-eye me-1"></i>Access Report
                    </a>
                    <a href="<?= h($dataObjectAccessManagementHref) ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-shield-lock me-1"></i>Manage Access
                    </a>
                    <a href="<?= h($dataObjectGrantHref) ?>" class="btn btn-sm btn-primary">
                      <i class="bi bi-person-plus me-1"></i>Grant Access
                    </a>
                  <?php else: ?>
                    <span class="text-muted small">Grant/revoke actions require Data Object Access administration permission.</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped table-hover table-admin table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Access Level</th>
                        <th>Children</th>
                        <th>Assigned At</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($dataObjectDirectAccess)): ?>
                        <tr>
                          <td colspan="5" class="text-center text-muted py-4">No direct Data Object Code grants found for this user.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($dataObjectDirectAccess as $grant): ?>
                          <?php
                            $grantLevel = (string)($grant['AccessLevel'] ?? '');
                            $includeChildren = (int)($grant['IncludeChildren'] ?? 0) === 1;
                          ?>
                          <tr>
                            <td class="fw-semibold"><?= h((string)($grant['DataObjectCode'] ?? '')) ?></td>
                            <td><?= h((string)($grant['DataObjectName'] ?? '')) ?></td>
                            <td>
                              <span class="badge <?= h($accessLevelBadgeClass($grantLevel)) ?>">
                                <?= h($formatAccessLevel($grantLevel)) ?>
                              </span>
                            </td>
                            <td><?= $includeChildren ? 'Included' : 'Direct only' ?></td>
                            <td><?= h((string)($grant['AssignedAt'] ?? '-')) ?></td>
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
                <h5 class="mb-0">Effective Data Object Code Access</h5>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped table-hover table-admin table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Level</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Parent</th>
                        <th>Source</th>
                        <th>Access Level</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($dataObjectAccess)): ?>
                        <tr>
                          <td colspan="6" class="text-center text-muted py-4">No effective Data Object Code access found for this user.</td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($dataObjectAccess as $accessRow): ?>
                          <?php
                            $accessLevel = (string)($accessRow['AccessLevel'] ?? '');
                            $source = (string)($accessRow['AccessSource'] ?? '');
                            $hierarchyLevel = (int)($accessRow['Level'] ?? 0);
                          ?>
                          <tr>
                            <td>
                              <span class="badge <?= $hierarchyLevel === 0 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $hierarchyLevel === 0 ? 'Direct' : 'L' . h((string)$hierarchyLevel) ?>
                              </span>
                            </td>
                            <td class="fw-semibold"><?= h((string)($accessRow['DataObjectCode'] ?? '')) ?></td>
                            <td><?= h((string)($accessRow['DataObjectName'] ?? '')) ?></td>
                            <td><?= h((string)($accessRow['ParentName'] ?? $accessRow['ParentCode'] ?? '-')) ?></td>
                            <td>
                              <span class="badge <?= $source === 'Direct' ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                <?= h($source !== '' ? $source : 'Unknown') ?>
                              </span>
                            </td>
                            <td>
                              <span class="badge <?= h($accessLevelBadgeClass($accessLevel)) ?>">
                                <?= h($formatAccessLevel($accessLevel)) ?>
                              </span>
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

          <hr class="mt-4 mb-2">
          <div class="d-flex justify-content-between align-items-center">
            <p class="text-muted small mb-0">
              <i class="bi bi-info-circle me-1"></i>
              Data Object Code access is managed from Organisation & Chart of Accounts.
            </p>
            <div class="d-flex gap-2">
              <a href="index.php?route=users/list<?= h($trainingScenarioQuery) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
              </a>
            </div>
          </div>
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
              <?= __t('form_save_hint') ?>
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

  document.querySelectorAll('[data-jump-tab]').forEach(link => {
    link.addEventListener('click', event => {
      const target = link.getAttribute('data-jump-tab') || '';
      const triggerEl = target ? document.querySelector(`button[data-bs-target="${target}"]`) : null;
      if (!triggerEl) { return; }
      event.preventDefault();
      new bootstrap.Tab(triggerEl).show();
      window.location.hash = target;
    });
  });
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

<?php if ($isCreateMode): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const emailInput = document.getElementById('Email');
  const inviteRadio = document.getElementById('OnboardingEmailInvite');
  const passwordRadio = document.getElementById('OnboardingTemporaryPassword');
  const passwordFields = document.getElementById('TemporaryPasswordFields');
  const passwordInput = document.getElementById('InitialPassword');
  const confirmInput = document.getElementById('InitialPasswordConfirm');

  if (!inviteRadio || !passwordRadio || !passwordFields || !passwordInput || !confirmInput) {
    return;
  }

  const syncOnboardingMode = () => {
    const temporaryPassword = passwordRadio.checked;
    passwordFields.classList.toggle('d-none', !temporaryPassword);
    passwordInput.required = temporaryPassword;
    confirmInput.required = temporaryPassword;
    if (emailInput) {
      emailInput.required = !temporaryPassword;
    }
  };

  inviteRadio.addEventListener('change', syncOnboardingMode);
  passwordRadio.addEventListener('change', syncOnboardingMode);
  syncOnboardingMode();
});
</script>
<?php endif; ?>
