<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$row = is_array($row ?? null) ? $row : [];
$id = (int)($row['EmailTemplateID'] ?? 0);
$tableInstalled = (bool)($tableInstalled ?? false);
$installScript = (string)($installScript ?? '');
$availableTokens = is_array($availableTokens ?? null) ? $availableTokens : [];
$screenHeader = [
    'title' => $id > 0 ? 'Edit Email Template' : 'Create Email Template',
    'icon' => 'bi-envelope-paper',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Configure reusable email content for application-generated messages.
      </div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <div class="fw-semibold mb-1">Email template table is not installed</div>
          <div class="small">Run the installer script before saving templates.</div>
          <?php if ($installScript !== ''): ?>
            <div class="mt-2"><code><?= h($installScript) ?></code></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=email-templates/save" class="needs-validation" novalidate>
        <?= csrf_field(); ?>
        <input type="hidden" name="EmailTemplateID" value="<?= h((string)$id) ?>">

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label for="TemplateKey" class="form-label">Template Key</label>
            <input id="TemplateKey" name="TemplateKey" class="form-control form-control-sm" required maxlength="100"
                   value="<?= h((string)($row['TemplateKey'] ?? '')) ?>" placeholder="USER_WELCOME_INVITE">
            <div class="invalid-feedback">Template key is required.</div>
          </div>
          <div class="col-md-5">
            <label for="TemplateName" class="form-label">Template Name</label>
            <input id="TemplateName" name="TemplateName" class="form-control form-control-sm" required maxlength="255"
                   value="<?= h((string)($row['TemplateName'] ?? '')) ?>" placeholder="New User Welcome Invite">
            <div class="invalid-feedback">Template name is required.</div>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" id="Active" name="Active" value="1" <?= ((int)($row['Active'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="Active">Active</label>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="Description" class="form-label">Description</label>
          <input id="Description" name="Description" class="form-control form-control-sm" maxlength="500"
                 value="<?= h((string)($row['Description'] ?? '')) ?>" placeholder="Where this template is used">
        </div>

        <div class="mb-3">
          <label for="Subject" class="form-label">Subject</label>
          <input id="Subject" name="Subject" class="form-control form-control-sm" required maxlength="255"
                 value="<?= h((string)($row['Subject'] ?? '')) ?>" placeholder="Welcome to {{APP_NAME}}">
          <div class="invalid-feedback">Subject is required.</div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-lg-8">
            <label for="BodyHtml" class="form-label">HTML Body</label>
            <textarea id="BodyHtml" name="BodyHtml" class="form-control form-control-sm font-monospace" rows="14" required><?= h((string)($row['BodyHtml'] ?? '')) ?></textarea>
            <div class="invalid-feedback">HTML body is required.</div>
          </div>
          <div class="col-lg-4">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Available Tokens</h5>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped table-hover table-admin table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Token</th>
                        <th>Description</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($availableTokens as $token => $description): ?>
                        <tr>
                          <td><code><?= h((string)$token) ?></code></td>
                          <td class="small"><?= h((string)$description) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label for="BodyText" class="form-label">Plain Text Body</label>
          <textarea id="BodyText" name="BodyText" class="form-control form-control-sm font-monospace" rows="8"><?= h((string)($row['BodyText'] ?? '')) ?></textarea>
        </div>

        <hr class="mt-4 mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <p class="text-muted small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Changes affect future queued emails only.
          </p>
          <div class="d-flex gap-2">
            <a href="index.php?route=email-templates/list" class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>Back
            </a>
            <button type="submit" class="btn btn-sm btn-primary" <?= $tableInstalled ? '' : 'disabled' ?>>
              <i class="bi bi-save me-1"></i>Save
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
