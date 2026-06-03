<?php declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$titleKey = $title ?? 'roles';
$roles = is_array($roles ?? null) ? $roles : [];

$areaOrder = [
    'Platform',
    'Strategic Framework',
    'Budget Submission',
    'Budget Execution',
    'Reporting',
    'Analytics',
    'Dashboards',
    'Configuration',
    'Administration',
    'Other',
];

$resolveArea = static function (string $roleName): string {
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
        default => 'Administration',
    };
};

$resolveLevel = static function (string $roleName): string {
    $name = trim($roleName);
    return match (true) {
        str_contains($name, 'Administrator'), $name === 'System Administrator' => 'Administrator',
        str_contains($name, 'Approver') => 'Approver',
        str_contains($name, 'Reviewer') => 'Reviewer',
        str_contains($name, 'Reporting User') => 'Reporting',
        str_contains($name, 'User') => 'User',
        $name === 'RatesEditor' => 'Specialist',
        default => 'Specialist',
    };
};

$groupedRoles = [];
foreach ($areaOrder as $area) {
    $groupedRoles[$area] = [];
}

$assignedRoleCount = 0;
$totalPermissionLinks = 0;
foreach ($roles as $role) {
    if (!is_array($role)) {
        continue;
    }
    $area = $resolveArea((string) ($role['RoleName'] ?? ''));
    if (!isset($groupedRoles[$area])) {
        $groupedRoles[$area] = [];
    }
    $groupedRoles[$area][] = $role;
    $assignedRoleCount += ((int) ($role['AssignedUserCount'] ?? 0) > 0) ? 1 : 0;
    $totalPermissionLinks += (int) ($role['PermissionCount'] ?? 0);
}
$groupedRoles = array_filter($groupedRoles, static fn(array $items): bool => $items !== []);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-shield-lock me-2"></i><?= h(__t($titleKey)) ?></h3>
        <div class="small text-muted mt-1">Review roles by functional area so access bundles match the business structure of the system.</div>
      </div>
      <a href="index.php?route=roles/edit" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= __t('create_role') ?>
      </a>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Role Catalogue</div>
              <div class="fs-4 fw-semibold"><?= count($roles) ?></div>
              <div class="small text-muted">Total roles available in the system.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Assigned Roles</div>
              <div class="fs-4 fw-semibold"><?= $assignedRoleCount ?></div>
              <div class="small text-muted">Roles currently linked to one or more users.</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Permission Links</div>
              <div class="fs-4 fw-semibold"><?= $totalPermissionLinks ?></div>
              <div class="small text-muted">Total role-to-permission assignments across all areas.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="small text-muted mb-3">Use the grouped sections below to review who each role is for, how widely it is assigned, and whether it already carries permission rules.</div>

      <?php if ($groupedRoles === []): ?>
        <div class="text-center text-muted py-3"><?= __t('no_records_found') ?></div>
      <?php else: ?>
        <?php foreach ($groupedRoles as $area => $items): ?>
          <?php
          $areaAssigned = 0;
          $areaPermissions = 0;
          foreach ($items as $item) {
              $areaAssigned += (int) ($item['AssignedUserCount'] ?? 0);
              $areaPermissions += (int) ($item['PermissionCount'] ?? 0);
          }
          ?>
          <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><?= h($area) ?></h5>
              <div class="d-inline-flex gap-2">
                <span class="badge text-bg-light border"><?= count($items) ?> role<?= count($items) === 1 ? '' : 's' ?></span>
                <span class="badge text-bg-light border"><?= $areaAssigned ?> assignment<?= $areaAssigned === 1 ? '' : 's' ?></span>
                <span class="badge text-bg-light border"><?= $areaPermissions ?> permission<?= $areaPermissions === 1 ? '' : 's' ?></span>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Role</th>
                      <th>Access Level</th>
                      <th>Status</th>
                      <th>Users</th>
                      <th>Permissions</th>
                      <th>Updated</th>
                      <th class="text-end"><?= __t('action') ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($items as $role): ?>
                      <?php
                      $roleId = (int) ($role['RoleID'] ?? 0);
                      $roleName = (string) ($role['RoleName'] ?? 'Unknown Role');
                      $userCount = (int) ($role['AssignedUserCount'] ?? 0);
                      $permissionCount = (int) ($role['PermissionCount'] ?? 0);
                      $level = $resolveLevel($roleName);
                      ?>
                      <tr>
                        <td>
                          <div><?= h($roleName) ?></div>
                          <div class="small text-muted">Role ID <?= $roleId ?></div>
                        </td>
                        <td><span class="badge text-bg-light border"><?= h($level) ?></span></td>
                        <td>
                          <?php if (!empty($role['Active'])): ?>
                            <span class="badge text-bg-success"><?= __t('active') ?></span>
                          <?php else: ?>
                            <span class="badge text-bg-secondary"><?= __t('inactive') ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div><?= $userCount ?></div>
                          <div class="small text-muted"><?= $userCount === 1 ? 'user assigned' : 'users assigned' ?></div>
                        </td>
                        <td>
                          <div><?= $permissionCount ?></div>
                          <div class="small text-muted"><?= $permissionCount === 1 ? 'permission linked' : 'permissions linked' ?></div>
                        </td>
                        <td><?= h((string) ($role['DateUpdated'] ?? '')) ?></td>
                        <td class="text-end">
                          <div class="d-inline-flex gap-1">
                            <a href="index.php?route=roles/edit&id=<?= $roleId ?>" class="btn btn-sm btn-outline-secondary" title="<?= __t('edit') ?>">
                              <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    title="<?= __t('delete') ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteRoleModal"
                                    data-roleid="<?= $roleId ?>"
                                    data-rolename="<?= h($roleName) ?>">
                              <i class="bi bi-trash"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="index.php?route=roles/delete" class="modal-content">
      <?= csrf_field(); ?>
      <input type="hidden" name="RoleID" id="deleteRoleID" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteRoleModalLabel">
          <i class="bi bi-exclamation-triangle me-2"></i><?= __t('delete') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __t('close') ?>"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1"><?= __t('confirm_delete_item') ?: 'Are you sure you want to delete' ?> <strong id="deleteRoleName"></strong>?</p>
        <p class="text-muted small mb-0"><?= __t('cannot_undo') ?: 'This action cannot be undone.' ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= __t('close') ?></button>
        <button type="submit" class="btn btn-danger btn-sm">
          <i class="bi bi-trash me-1"></i><?= __t('delete') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('deleteRoleModal');
  const idField = document.getElementById('deleteRoleID');
  const nameSpan = document.getElementById('deleteRoleName');
  modal?.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget;
    if (!btn) { return; }
    idField.value = btn.getAttribute('data-roleid') || '';
    nameSpan.textContent = btn.getAttribute('data-rolename') || '';
  });
});
</script>
