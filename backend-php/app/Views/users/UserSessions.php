<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-activity me-2"></i><?= h($title ?? 'Active User Sessions') ?></h3>
      </div>
      <a href="index.php?route=users/list" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= __t('back') ?></a>
    </div>

    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __t('close') ?>"></button>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Review active login sessions and terminate a session when administrative intervention is needed.</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Username</th>
              <th>IP Address</th>
              <th>Login Time</th>
              <th>Last Activity</th>
              <th>Expires At</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">No active sessions found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <?php $isActive = (int) ($r['IsActive'] ?? 0) === 1; ?>
                <tr>
                  <td><?= h((string) ($r['Username'] ?? '')) ?></td>
                  <td><?= h((string) ($r['IP'] ?? '')) ?></td>
                  <td><?= h((string) ($r['LoginTime'] ?? '')) ?></td>
                  <td><?= h((string) ($r['LastActivity'] ?? '')) ?></td>
                  <td><?= h((string) ($r['ExpiresAt'] ?? '')) ?></td>
                  <td>
                    <?php if ($isActive): ?>
                      <span class="badge text-bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#forceLogoutModal"
                            data-session="<?= h((string) ($r['SessionID'] ?? '')) ?>"
                            data-username="<?= h((string) ($r['Username'] ?? '')) ?>">
                      <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </button>
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

<div class="modal fade" id="forceLogoutModal" tabindex="-1" aria-labelledby="forceLogoutLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="index.php?route=sessions/forcelogout">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" id="forceLogoutSessionID" name="SessionID" value="">

        <div class="modal-header">
          <h5 class="modal-title" id="forceLogoutLabel"><i class="bi bi-box-arrow-right me-2"></i>Force Logout</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2">Are you sure you want to force logout this user session?</p>
          <p class="fw-semibold mb-0" id="logoutUsername"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-power me-1"></i>Confirm Logout
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('forceLogoutModal');
  modal?.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    if (!button) { return; }
    const sessionId = button.getAttribute('data-session') || '';
    const username = button.getAttribute('data-username') || '';
    const idField = modal.querySelector('#forceLogoutSessionID');
    const nameTarget = modal.querySelector('#logoutUsername');
    if (idField) { idField.value = sessionId; }
    if (nameTarget) { nameTarget.textContent = username ? 'User: ' + username : ''; }
  });
});
</script>
