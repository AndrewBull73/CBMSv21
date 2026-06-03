<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$authTypeOptions = is_array($authTypeOptions ?? null) ? $authTypeOptions : [];
$environmentOptions = is_array($environmentOptions ?? null) ? $environmentOptions : [];
$id = (int) ($record['IntegrationSystemID'] ?? 0);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-hdd-network me-2"></i><?= $id > 0 ? 'Edit Integration System' : 'Create Integration System' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> before creating integration systems.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Define the external platform identity, connection shape, and credential reference for downstream interfaces.
        </div>

        <form method="post" action="index.php?route=integration-admin/save-system">
          <?= csrf_field() ?>
          <input type="hidden" name="IntegrationSystemID" value="<?= $id ?>">

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">System Code</label>
              <input type="text" name="SystemCode" class="form-control" value="<?= h((string) ($record['SystemCode'] ?? '')) ?>" required placeholder="FINANCE_A">
            </div>
            <div class="col-md-5">
              <label class="form-label">System Name</label>
              <input type="text" name="SystemName" class="form-control" value="<?= h((string) ($record['SystemName'] ?? '')) ?>" required placeholder="Finance System A">
            </div>
            <div class="col-md-3">
              <label class="form-label">Environment</label>
              <select name="EnvironmentCode" class="form-select">
                <?php foreach ($environmentOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['EnvironmentCode'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Base URL</label>
              <input type="text" name="BaseUrl" class="form-control" value="<?= h((string) ($record['BaseUrl'] ?? '')) ?>" placeholder="https://finance-a.example/api">
            </div>
            <div class="col-md-3">
              <label class="form-label">Auth Type</label>
              <select name="AuthType" class="form-select">
                <?php foreach ($authTypeOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['AuthType'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Credential Reference</label>
              <input type="text" name="CredentialReference" class="form-control" value="<?= h((string) ($record['CredentialReference'] ?? '')) ?>" placeholder="vault://finance-a/prod">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Default Headers JSON</label>
            <textarea name="DefaultHeadersJson" class="form-control font-monospace" rows="4" placeholder='{"x-api-key":"{{secret}}","accept":"application/json"}'><?= h((string) ($record['DefaultHeadersJson'] ?? '')) ?></textarea>
            <div class="form-text">Optional shared headers for interfaces on this system.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="Notes" class="form-control" rows="4" placeholder="Document the owning team, refresh cadence, approval boundaries, or anything else future implementers should know."><?= h((string) ($record['Notes'] ?? '')) ?></textarea>
          </div>

          <div class="form-check mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ActiveFlag">System is active and available for interface registration</label>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <a href="index.php?route=integration-admin/systems" class="btn btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save System</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
