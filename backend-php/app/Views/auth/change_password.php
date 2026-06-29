<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$mustChange = (bool)($mustChange ?? false);
$screenHeader = [
    'title' => $mustChange ? 'Set Password' : 'Change Password',
    'icon' => 'bi-key',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= $mustChange
            ? 'Set a new password before continuing into CBMSv21.'
            : 'Update your account password.' ?>
      </div>

      <form method="post" action="index.php?route=auth/savePassword" class="needs-validation" novalidate autocomplete="off">
        <?= csrf_field(); ?>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="NewPassword" class="form-label">New Password</label>
            <input type="password" id="NewPassword" name="NewPassword" class="form-control form-control-sm" required minlength="10" autocomplete="new-password">
            <div class="invalid-feedback">Enter a new password of at least 10 characters.</div>
          </div>
          <div class="col-md-6">
            <label for="ConfirmPassword" class="form-label">Confirm Password</label>
            <input type="password" id="ConfirmPassword" name="ConfirmPassword" class="form-control form-control-sm" required minlength="10" autocomplete="new-password">
            <div class="invalid-feedback">Confirm the new password.</div>
          </div>
        </div>

        <div class="alert alert-info border-0 shadow-sm mb-4">
          <div class="fw-semibold mb-1">Password Requirements</div>
          <div class="small text-muted">Use at least 10 characters including uppercase, lowercase, and numeric characters.</div>
        </div>

        <hr class="mt-4 mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <p class="text-muted small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            The new password takes effect immediately after saving.
          </p>
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-save me-1"></i>Save Password
          </button>
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
