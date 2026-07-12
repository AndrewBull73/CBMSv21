<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$systemOptions = is_array($systemOptions ?? null) ? $systemOptions : [];
$interfaceOptions = is_array($interfaceOptions ?? null) ? $interfaceOptions : [];
$mappingTypeOptions = is_array($mappingTypeOptions ?? null) ? $mappingTypeOptions : [];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$mappingInstalled = (bool) ($mappingInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h3 class="mb-0"><i class="bi bi-shuffle me-2"></i>Integration Code Crosswalks</h3>
        <div class="small text-muted mt-1">Map external FMIS, HR, payroll, or other source codes to CBMS codes.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=integration-admin/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="index.php?route=integration-admin/actuals-review" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check me-1"></i>Actuals Review</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run the integration foundation SQL before maintaining crosswalks.</div>
      <?php elseif (!$mappingInstalled): ?>
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> before maintaining integration code crosswalks.
        </div>
      <?php else: ?>
        <div class="alert alert-info">
          Crosswalks are used by import review and postability checks. If any active mapping exists for a system and mapping type, unmapped incoming codes of that type are treated as blocked.
        </div>

        <form method="post" action="index.php?route=integration-admin/save-crosswalk" class="border rounded p-3 mb-4 bg-white">
          <?= csrf_field() ?>
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label">System</label>
              <select name="IntegrationSystemID" class="form-select" required>
                <option value="">Select system</option>
                <?php foreach ($systemOptions as $option): ?>
                  <option value="<?= (int) ($option['IntegrationSystemID'] ?? 0) ?>">
                    <?= h((string) (($option['SystemName'] ?? '') . ' [' . ($option['SystemCode'] ?? '') . ']')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Interface</label>
              <select name="IntegrationInterfaceID" class="form-select">
                <option value="">Any interface</option>
                <?php foreach ($interfaceOptions as $option): ?>
                  <option value="<?= (int) ($option['IntegrationInterfaceID'] ?? 0) ?>">
                    <?= h((string) (($option['SystemCode'] ?? '') . ' / ' . ($option['InterfaceName'] ?? '') . ' [' . ($option['InterfaceCode'] ?? '') . ']')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Mapping Type</label>
              <select name="MappingTypeCode" class="form-select" required>
                <?php foreach ($mappingTypeOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>"><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">External Code</label>
              <input type="text" name="ExternalCode" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">CBMS Code</label>
              <input type="text" name="CbmsCode" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">External Description</label>
              <input type="text" name="ExternalDescription" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">CBMS Description</label>
              <input type="text" name="CbmsDescription" class="form-control">
            </div>
            <div class="col-md-1">
              <label class="form-label">FY</label>
              <input type="number" name="FiscalYearID" class="form-control">
            </div>
            <div class="col-md-1">
              <label class="form-label">Ver</label>
              <input type="number" name="VersionID" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Effective From</label>
              <input type="date" name="EffectiveFrom" class="form-control">
            </div>
            <div class="col-md-2">
              <label class="form-label">Effective To</label>
              <input type="date" name="EffectiveTo" class="form-control">
            </div>
            <div class="col-md-2">
              <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="ActiveFlag" value="1" id="CrosswalkActiveFlag" checked>
                <label class="form-check-label" for="CrosswalkActiveFlag">Active</label>
              </div>
            </div>
            <div class="col-md-10">
              <label class="form-label">Notes</label>
              <input type="text" name="Notes" class="form-control">
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Crosswalk</button>
            </div>
          </div>
        </form>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="integration-admin/crosswalks">
          <div class="col-md-3">
            <input type="search" name="q" class="form-control" placeholder="Search code or description" value="<?= h((string) ($filters['q'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <select name="system_id" class="form-select">
              <option value="">All systems</option>
              <?php foreach ($systemOptions as $option): ?>
                <option value="<?= (int) ($option['IntegrationSystemID'] ?? 0) ?>" <?= ((string) ($filters['system_id'] ?? '') === (string) ($option['IntegrationSystemID'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) ($option['SystemCode'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="mapping_type" class="form-select">
              <option value="">All types</option>
              <?php foreach ($mappingTypeOptions as $value => $label): ?>
                <option value="<?= h((string) $value) ?>" <?= ((string) ($filters['mapping_type'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
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
          <div class="col-md-2 d-grid">
            <a href="index.php?route=integration-admin/crosswalks" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>System</th>
                <th>Interface</th>
                <th>Type</th>
                <th>External</th>
                <th>CBMS</th>
                <th>Context</th>
                <th>Effective</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No crosswalk rows matched the current filters.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['SystemCode'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['SystemName'] ?? '')) ?></div>
                    </td>
                    <td><?= h((string) (($row['InterfaceCode'] ?? '') !== '' ? $row['InterfaceCode'] : 'Any interface')) ?></td>
                    <td><?= h((string) ($mappingTypeOptions[(string) ($row['MappingTypeCode'] ?? '')] ?? ($row['MappingTypeCode'] ?? ''))) ?></td>
                    <td>
                      <div class="font-monospace"><?= h((string) ($row['ExternalCode'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ExternalDescription'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div class="font-monospace"><?= h((string) ($row['CbmsCode'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['CbmsDescription'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div><?= !empty($row['FiscalYearID']) ? ('FY ' . h((string) $row['FiscalYearID'])) : 'Any FY' ?></div>
                      <div class="small text-muted"><?= !empty($row['VersionID']) ? ('Version ' . h((string) $row['VersionID'])) : 'Any version' ?></div>
                    </td>
                    <td>
                      <div><?= h((string) ($row['EffectiveFrom'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['EffectiveTo'] ?? '')) ?></div>
                    </td>
                    <td>
                      <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                      </span>
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
