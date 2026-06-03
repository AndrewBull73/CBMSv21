<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'active' => '1'];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Integration Systems</h3>
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
          Run <code><?= h($installScriptPath) ?></code> to install the integration foundation tables before maintaining systems.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Use one system record per external platform, environment, or credential boundary. The system code will also be the adapter lookup key for real import/export implementations.
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div class="small text-muted">
            Register each finance platform once, then define multiple inbound and outbound interfaces against it.
          </div>
          <a href="index.php?route=integration-admin/system-form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create System</a>
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="integration-admin/systems">
          <div class="col-md-7">
            <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Search code, name, base URL, or auth type">
          </div>
          <div class="col-md-3">
            <select name="active" class="form-select">
              <option value="">All statuses</option>
              <option value="1" <?= ((string) ($filters['active'] ?? '') === '1') ? 'selected' : '' ?>>Active only</option>
              <option value="0" <?= ((string) ($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-fill">Filter</button>
            <a href="index.php?route=integration-admin/systems" class="btn btn-outline-secondary flex-fill">Reset</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>System</th>
                <th>Environment</th>
                <th>Auth</th>
                <th>Base URL</th>
                <th>Interfaces</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No integration systems have been registered yet.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['SystemName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['SystemCode'] ?? '')) ?></div>
                    </td>
                    <td><?= h((string) ($row['EnvironmentCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['AuthType'] ?? '')) ?></td>
                    <td><code><?= h((string) ($row['BaseUrl'] ?? '')) ?></code></td>
                    <td><?= (int) ($row['InterfaceCount'] ?? 0) ?></td>
                    <td>
                      <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <a href="index.php?route=integration-admin/system-form&id=<?= (int) ($row['IntegrationSystemID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
