<?php declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$groupedRows = is_array($groupedRows ?? null) ? $groupedRows : [];
$modules = is_array($modules ?? null) ? $modules : [];
$functionalAreas = is_array($functionalAreas ?? null) ? $functionalAreas : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$roles = is_array($roles ?? null) ? $roles : [];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-grid me-2"></i>Access Matrix</h3>
      </div>
      <a href="index.php?route=roles/list" class="btn btn-sm btn-primary"><i class="bi bi-shield-lock me-1"></i>Roles & Permissions</a>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-3">
        <div class="col-md-2 col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Routes</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['route_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-2 col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Permission Controlled</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['permission_controlled_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-2 col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Auth Only</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['auth_only_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-2 col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Public</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['public_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-2 col-sm-4">
          <div class="card shadow-sm h-100">
            <div class="card-body py-3">
              <div class="small text-muted">Menu Uses Roles</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['menu_role_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <form method="get" class="row g-2 mb-3">
        <input type="hidden" name="route" value="access-matrix/index">
        <div class="col-md-3">
          <input type="text" name="q" class="form-control form-control-sm" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search route, screen, or controller">
        </div>
        <div class="col-md-2">
          <select name="module" class="form-select form-select-sm">
            <option value="">All modules</option>
            <?php foreach ($modules as $module): ?>
              <option value="<?= h((string) $module) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $module) ? 'selected' : '' ?>><?= h((string) $module) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="functional_area" class="form-select form-select-sm">
            <option value="">All areas</option>
            <?php foreach ($functionalAreas as $functionalArea): ?>
              <option value="<?= h((string) $functionalArea) ?>" <?= ((string) ($filters['functional_area'] ?? '') === (string) $functionalArea) ? 'selected' : '' ?>><?= h((string) $functionalArea) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="permission" class="form-select form-select-sm">
            <option value="">All permissions</option>
            <?php foreach ($permissions as $permission): ?>
              <option value="<?= h((string) $permission) ?>" <?= ((string) ($filters['permission'] ?? '') === (string) $permission) ? 'selected' : '' ?>><?= h((string) $permission) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1">
          <select name="role" class="form-select form-select-sm">
            <option value="">All roles</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?= h((string) $role) ?>" <?= ((string) ($filters['role'] ?? '') === (string) $role) ? 'selected' : '' ?>><?= h((string) $role) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="access_type" class="form-select form-select-sm">
            <option value="">All types</option>
            <?php foreach (['Permission Controlled', 'Auth Only', 'Public'] as $type): ?>
              <option value="<?= h($type) ?>" <?= ((string) ($filters['access_type'] ?? '') === $type) ? 'selected' : '' ?>><?= h($type) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
          <a href="index.php?route=access-matrix/index" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="small text-muted mb-3">Use this screen to understand what permissions a screen requires, where menu visibility still depends on roles, and which current roles satisfy the enforced controller rules.</div>

      <?php if ($groupedRows === []): ?>
        <div class="text-center text-muted py-3">No access records matched the current filters.</div>
      <?php else: ?>
        <?php foreach ($groupedRows as $group): ?>
          <?php
          $groupSummary = is_array($group['summary'] ?? null) ? $group['summary'] : [];
          $groupRows = is_array($group['rows'] ?? null) ? $group['rows'] : [];
          $groupName = (string) ($group['functional_area'] ?? 'Other');
          ?>
          <div class="card shadow-sm mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <h5 class="mb-0"><?= h($groupName) ?></h5>
              <div class="d-inline-flex flex-wrap gap-2">
                <span class="badge text-bg-light border"><?= (int) ($groupSummary['route_count'] ?? 0) ?> routes</span>
                <span class="badge text-bg-light border">Permission Controlled: <?= (int) ($groupSummary['permission_controlled_count'] ?? 0) ?></span>
                <span class="badge text-bg-light border">Auth Only: <?= (int) ($groupSummary['auth_only_count'] ?? 0) ?></span>
                <span class="badge text-bg-light border">Menu Uses Roles: <?= (int) ($groupSummary['menu_role_count'] ?? 0) ?></span>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Screen</th>
                      <th>Route</th>
                      <th>Controller</th>
                      <th>Controller Access</th>
                      <th>Roles That Pass</th>
                      <th>Menu Access</th>
                      <th>Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($groupRows as $row): ?>
                      <tr>
                        <td>
                          <div><?= h((string) ($row['screen_label'] ?? '')) ?></div>
                          <?php if ((string) ($row['menu_path'] ?? '') !== ''): ?>
                            <div class="small text-muted"><?= h((string) ($row['menu_path'] ?? '')) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><code><?= h((string) ($row['route'] ?? '')) ?></code></td>
                        <td>
                          <div><code><?= h((string) ($row['controller'] ?? '')) ?>@<?= h((string) ($row['action'] ?? '')) ?></code></div>
                          <div class="small text-muted"><?= h((string) ($row['module'] ?? '')) ?></div>
                        </td>
                        <td>
                          <div><span class="badge text-bg-light border"><?= h((string) ($row['access_type'] ?? '')) ?></span></div>
                          <?php if (($row['controller_perms_any'] ?? []) !== []): ?>
                            <div class="small mt-1"><strong>permsAny</strong>: <?= h(implode(', ', $row['controller_perms_any'])) ?></div>
                          <?php endif; ?>
                          <?php if (($row['controller_perms_all'] ?? []) !== []): ?>
                            <div class="small mt-1"><strong>permsAll</strong>: <?= h(implode(', ', $row['controller_perms_all'])) ?></div>
                          <?php endif; ?>
                          <?php if (($row['controller_perms_any'] ?? []) === [] && ($row['controller_perms_all'] ?? []) === []): ?>
                            <div class="small text-muted mt-1"><?= !empty($row['auth_required']) ? 'Authenticated users only.' : 'Public route.' ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (($row['controller_roles'] ?? []) !== []): ?>
                            <div class="small"><?= h(implode(', ', $row['controller_roles'])) ?></div>
                          <?php else: ?>
                            <div class="small text-muted">No direct permission match to list.</div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (($row['menu_perms'] ?? []) !== []): ?>
                            <div class="small"><strong>Perms</strong>: <?= h(implode(', ', $row['menu_perms'])) ?></div>
                          <?php endif; ?>
                          <?php if (($row['menu_roles'] ?? []) !== []): ?>
                            <div class="small mt-1"><strong>Roles</strong>: <?= h(implode(', ', $row['menu_roles'])) ?></div>
                          <?php endif; ?>
                          <?php if (($row['menu_perms'] ?? []) === [] && ($row['menu_roles'] ?? []) === []): ?>
                            <div class="small text-muted">No explicit menu restriction found.</div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if (($row['notes'] ?? []) !== []): ?>
                            <?php foreach ($row['notes'] as $note): ?>
                              <div class="small text-muted"><?= h((string) $note) ?></div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="small text-muted">Aligned.</div>
                          <?php endif; ?>
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
