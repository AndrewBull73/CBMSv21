<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'system_id' => '', 'direction' => '', 'active' => '1'];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$systemOptions = is_array($systemOptions ?? null) ? $systemOptions : [];
$directionOptions = is_array($directionOptions ?? null) ? $directionOptions : [];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Integration Interfaces</h3>
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
          Run <code><?= h($installScriptPath) ?></code> to install the integration foundation tables before maintaining interfaces.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          An interface is one concrete data flow, such as "Import daily actuals from Finance A" or "Export approved budget to Finance B".
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div class="small text-muted">
            Define each import or export contract separately so daily actuals and budget exports can evolve independently.
          </div>
          <a href="index.php?route=integration-admin/interface-form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Interface</a>
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="integration-admin/interfaces">
          <div class="col-md-4">
            <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Search code, name, module, entity, or system">
          </div>
          <div class="col-md-3">
            <select name="system_id" class="form-select">
              <option value="">All systems</option>
              <?php foreach ($systemOptions as $option): ?>
                <option value="<?= (int) ($option['IntegrationSystemID'] ?? 0) ?>" <?= ((string) ($filters['system_id'] ?? '') === (string) ($option['IntegrationSystemID'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) (($option['SystemName'] ?? '') . ' [' . ($option['SystemCode'] ?? '') . ']')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="direction" class="form-select">
              <option value="">All directions</option>
              <?php foreach ($directionOptions as $value => $label): ?>
                <option value="<?= h((string) $value) ?>" <?= ((string) ($filters['direction'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="active" class="form-select">
              <option value="">All statuses</option>
              <option value="1" <?= ((string) ($filters['active'] ?? '') === '1') ? 'selected' : '' ?>>Active only</option>
              <option value="0" <?= ((string) ($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
            </select>
          </div>
          <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Interface</th>
                <th>System</th>
                <th>Direction</th>
                <th>Module / Entity</th>
                <th>Readiness</th>
                <th>Endpoint</th>
                <th>Trigger</th>
                <th>Last Run</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="10" class="text-center text-muted py-3">No interfaces matched the current filters.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                  $lastStatus = trim((string) ($row['LastRunStatusCode'] ?? ''));
                  $directionCode = strtolower(trim((string) ($row['DirectionCode'] ?? '')));
                  $canTestExport = in_array($directionCode, ['outbound', 'bidirectional'], true);
                  $readinessStatus = trim((string) ($row['ReadinessStatus'] ?? ''));
                  $readinessClass = match (strtolower($readinessStatus)) {
                      'production_ready' => 'text-bg-success',
                      'uat_ready', 'test_ready' => 'text-bg-primary',
                      'blocked' => 'text-bg-danger',
                      'drafting' => 'text-bg-warning',
                      default => 'text-bg-light border',
                  };
                  $lastStatusClass = match (strtolower($lastStatus)) {
                      'success' => 'text-bg-success',
                      'warning' => 'text-bg-warning',
                      'failed' => 'text-bg-danger',
                      'running' => 'text-bg-primary',
                      default => 'text-bg-light border',
                  };
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['InterfaceName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['InterfaceCode'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div><?= h((string) ($row['SystemName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['SystemCode'] ?? '')) ?></div>
                    </td>
                    <td><?= h((string) ($directionOptions[(string) ($row['DirectionCode'] ?? '')] ?? ($row['DirectionCode'] ?? ''))) ?></td>
                    <td><?= h(trim((string) (($row['ModuleCode'] ?? '') . ' / ' . ($row['EntityCode'] ?? '')), ' /')) ?></td>
                    <td>
                      <div><span class="badge <?= h($readinessClass) ?>"><?= h($readinessStatus !== '' ? $readinessStatus : 'not set') ?></span></div>
                      <div class="small text-muted"><?= h((string) ($row['BusinessOwner'] ?? '')) ?></div>
                    </td>
                    <td><code><?= h((string) ($row['EndpointPath'] ?? '')) ?></code></td>
                    <td><?= h((string) ($row['TriggerMode'] ?? '')) ?></td>
                    <td>
                      <?php if (!empty($row['LastRunStartedAt'])): ?>
                        <div><?= h((string) $row['LastRunStartedAt']) ?></div>
                        <div class="small"><span class="badge <?= h($lastStatusClass) ?>"><?= h($lastStatus !== '' ? $lastStatus : 'Unknown') ?></span></div>
                      <?php else: ?>
                        <span class="text-muted">No runs yet</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                        <?php if ($canTestExport): ?>
                          <a href="index.php?route=integration-admin/test-export&id=<?= (int) ($row['IntegrationInterfaceID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="Test export runner">
                            <i class="bi bi-play-circle"></i>
                          </a>
                        <?php endif; ?>
                        <a href="index.php?route=integration-admin/interface-form&id=<?= (int) ($row['IntegrationInterfaceID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary" title="Edit interface">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      </div>
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
