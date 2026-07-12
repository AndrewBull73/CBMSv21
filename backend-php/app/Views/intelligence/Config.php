<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$providers = array_values(is_array($providers ?? null) ? $providers : []);
$moduleSettings = array_values(is_array($moduleSettings ?? null) ? $moduleSettings : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-sliders me-2"></i>Intelligence and AI Configuration</h3>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/index">Dashboard</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="alert alert-info py-2">Runtime engine URL is read from <code>INTELLIGENCE_ENGINE_URL</code> in <code>.env</code>. Current value: <strong><?= h((string) $engineUrl) ?></strong></div>
        <h4 class="h6">AI Providers</h4>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Provider</th><th>Type</th><th>Base URL</th><th>Model</th><th>Sensitive Data</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $row): ?>
              <tr>
                <td><div class="fw-semibold"><?= h((string) ($row['ProviderName'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['ProviderCode'] ?? '')) ?></div></td>
                <td><?= h((string) ($row['ProviderTypeCode'] ?? '')) ?></td>
                <td class="small"><?= h((string) ($row['BaseUrl'] ?? '')) ?></td>
                <td><?= h((string) ($row['ModelCode'] ?? '')) ?></td>
                <td><?= (int) ($row['AllowsSensitiveData'] ?? 0) === 1 ? 'Allowed' : 'Blocked' ?></td>
                <td><?= (int) ($row['ActiveFlag'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <h4 class="h6">Module Routing</h4>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Module</th><th>Provider</th><th>Sensitivity Rule</th><th>External AI</th><th>Masking</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($moduleSettings as $row): ?>
              <tr>
                <td class="fw-semibold"><?= h((string) ($row['ModuleCode'] ?? '')) ?></td>
                <td><?= h((string) ($row['ProviderCode'] ?? '')) ?></td>
                <td><?= h((string) ($row['SensitivityRuleCode'] ?? '')) ?></td>
                <td><?= (int) ($row['AllowExternalAI'] ?? 0) === 1 ? 'Allowed' : 'Blocked' ?></td>
                <td><?= (int) ($row['MaskExternalData'] ?? 0) === 1 ? 'Enabled' : 'Disabled' ?></td>
                <td><?= (int) ($row['ActiveFlag'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
