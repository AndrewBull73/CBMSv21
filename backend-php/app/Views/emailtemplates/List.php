<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$rows = is_array($rows ?? null) ? $rows : [];
$tableInstalled = (bool)($tableInstalled ?? false);
$installScript = (string)($installScript ?? '');
$lastError = trim((string)($lastError ?? ''));
$screenHeader = [
    'title' => 'Email Templates',
    'icon' => 'bi-envelope-paper',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Maintain reusable email templates used by onboarding and other application notifications.
      </div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <div class="fw-semibold mb-1">Email template table is not installed</div>
          <div class="small">Run the installer script before creating or editing templates.</div>
          <?php if ($installScript !== ''): ?>
            <div class="mt-2"><code><?= h($installScript) ?></code></div>
          <?php endif; ?>
          <?php if ($lastError !== ''): ?>
            <div class="small text-muted mt-2"><?= h($lastError) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Email Template Runbook</div>
        <div class="mb-2">Use template keys for application email flows, then update subject and body content here without changing PHP code.</div>
        <div class="small text-muted">The user onboarding flow uses <code>USER_WELCOME_INVITE</code> when a new account is created with the email invite option.</div>
      </div>

      <div class="d-flex justify-content-end mb-3">
        <a href="index.php?route=email-templates/form" class="btn btn-sm btn-primary<?= $tableInstalled ? '' : ' disabled' ?>">
          <i class="bi bi-plus-circle me-1"></i>Create Template
        </a>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover table-admin table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Template Key</th>
              <th>Name</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Updated</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$tableInstalled): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">Install the email template table to manage templates.</td>
              </tr>
            <?php elseif ($rows === []): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No email templates found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $id = (int)($row['EmailTemplateID'] ?? 0);
                  $active = (int)($row['Active'] ?? 0) === 1;
                ?>
                <tr>
                  <td><code><?= h((string)($row['TemplateKey'] ?? '')) ?></code></td>
                  <td>
                    <div class="fw-semibold"><?= h((string)($row['TemplateName'] ?? '')) ?></div>
                    <?php if (trim((string)($row['Description'] ?? '')) !== ''): ?>
                      <div class="small text-muted"><?= h((string)$row['Description']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)($row['Subject'] ?? '')) ?></td>
                  <td>
                    <span class="badge <?= $active ? 'text-bg-success' : 'text-bg-secondary' ?>">
                      <?= $active ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td><?= h((string)($row['UpdatedAt'] ?? $row['CreatedAt'] ?? '-')) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-2">
                      <a href="index.php?route=email-templates/form&id=<?= h((string)$id) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                      </a>
                      <form method="post" action="index.php?route=email-templates/setActive" class="d-inline">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="EmailTemplateID" value="<?= h((string)$id) ?>">
                        <input type="hidden" name="Active" value="<?= $active ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm <?= $active ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                          <i class="bi <?= $active ? 'bi-pause-circle' : 'bi-play-circle' ?> me-1"></i><?= $active ? 'Disable' : 'Enable' ?>
                        </button>
                      </form>
                    </div>
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
