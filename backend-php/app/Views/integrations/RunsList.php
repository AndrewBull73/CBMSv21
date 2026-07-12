<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['system_id' => '', 'interface_id' => '', 'status' => ''];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$systemOptions = is_array($systemOptions ?? null) ? $systemOptions : [];
$interfaceOptions = is_array($interfaceOptions ?? null) ? $interfaceOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-clock-history me-2"></i>Integration Run History</h3>
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
          Run <code><?= h($installScriptPath) ?></code> to install the integration foundation tables before reviewing run history.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          The run log schema is ready now. Real execution records will start appearing here once we wire the first actual import/export jobs into the engine.
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="integration-admin/runs">
          <div class="col-md-4">
            <select name="system_id" class="form-select">
              <option value="">All systems</option>
              <?php foreach ($systemOptions as $option): ?>
                <option value="<?= (int) ($option['IntegrationSystemID'] ?? 0) ?>" <?= ((string) ($filters['system_id'] ?? '') === (string) ($option['IntegrationSystemID'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) (($option['SystemName'] ?? '') . ' [' . ($option['SystemCode'] ?? '') . ']')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <select name="interface_id" class="form-select">
              <option value="">All interfaces</option>
              <?php foreach ($interfaceOptions as $option): ?>
                <option value="<?= (int) ($option['IntegrationInterfaceID'] ?? 0) ?>" <?= ((string) ($filters['interface_id'] ?? '') === (string) ($option['IntegrationInterfaceID'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) (($option['SystemCode'] ?? '') . ' / ' . ($option['InterfaceName'] ?? '') . ' [' . ($option['InterfaceCode'] ?? '') . ']')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="status" class="form-select">
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= h((string) $value) ?>" <?= ((string) ($filters['status'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
              <?php endforeach; ?>
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
                <th>Started</th>
                <th>Interface</th>
                <th>Status</th>
                <th>Trigger</th>
                <th>Scope</th>
                <th>Records</th>
                <th>Summary</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No integration runs have been recorded yet.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                  $status = strtolower(trim((string) ($row['RunStatusCode'] ?? '')));
                  $statusClass = match ($status) {
                      'success' => 'text-bg-success',
                      'warning' => 'text-bg-warning',
                      'failed' => 'text-bg-danger',
                      'running' => 'text-bg-primary',
                      'queued' => 'text-bg-secondary',
                      default => 'text-bg-light border',
                  };
                  $scopeParts = array_filter([
                      !empty($row['FiscalYearID']) ? 'FY ' . $row['FiscalYearID'] : '',
                      !empty($row['VersionID']) ? 'Version ' . $row['VersionID'] : '',
                      !empty($row['DataObjectCode']) ? 'Scope ' . $row['DataObjectCode'] : '',
                  ]);
                  $isPreviewOnly = (string) ($row['TriggerSourceCode'] ?? '') === 'manual_preview';
                  $isMockSystem = str_starts_with(strtoupper((string) ($row['SystemCode'] ?? '')), 'MOCK_');
                  ?>
                  <tr>
                    <td>
                      <div><?= h((string) ($row['StartedAt'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['CompletedAt'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['InterfaceName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) (($row['SystemCode'] ?? '') . ' / ' . ($row['InterfaceCode'] ?? ''))) ?></div>
                      <div class="small text-muted"><?= h(trim((string) (($row['DirectionCode'] ?? '') . ' ' . ($row['ModuleCode'] ?? '') . ' ' . ($row['EntityCode'] ?? '')))) ?></div>
                      <?php if ($isMockSystem): ?>
                        <div class="small mt-1"><span class="badge text-bg-warning">Mock system</span></div>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge <?= h($statusClass) ?>"><?= h((string) ($row['RunStatusCode'] ?? '')) ?></span></td>
                    <td>
                      <div><?= h((string) ($row['TriggerSourceCode'] ?? '')) ?></div>
                      <?php if ($isPreviewOnly): ?>
                        <div class="small"><span class="badge text-bg-warning">No external dispatch</span></div>
                      <?php endif; ?>
                      <div class="small text-muted"><?= h((string) (($row['DisplayName'] ?? '') !== '' ? $row['DisplayName'] : ($row['Username'] ?? ''))) ?></div>
                    </td>
                    <td><?= h($scopeParts !== [] ? implode(' | ', $scopeParts) : 'N/A') ?></td>
                    <td>
                      <div>Received: <?= (int) ($row['RecordsReceived'] ?? 0) ?></div>
                      <div class="small text-muted">Processed: <?= (int) ($row['RecordsProcessed'] ?? 0) ?> | Failed: <?= (int) ($row['RecordsFailed'] ?? 0) ?></div>
                    </td>
                    <td>
                      <div><?= h((string) ($row['SummaryText'] ?? '')) ?></div>
                      <?php if (!empty($row['ErrorText'])): ?>
                        <div class="small text-danger mt-1"><?= h((string) $row['ErrorText']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                        <a href="index.php?route=integration-admin/run-detail&id=<?= (int) ($row['IntegrationRunID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary" title="View run detail">
                          <i class="bi bi-file-earmark-text"></i>
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
