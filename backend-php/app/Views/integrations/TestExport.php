<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$interface = is_array($interface ?? null) ? $interface : [];
$mappingConfig = is_array($mappingConfig ?? null) ? $mappingConfig : [];
$formData = is_array($formData ?? null) ? $formData : [];
$previewResult = is_array($previewResult ?? null) ? $previewResult : null;
$dispatchResult = is_array($dispatchResult ?? null) ? $dispatchResult : null;
$recentRuns = is_array($recentRuns ?? null) ? $recentRuns : [];
$outputProfileOptions = is_array($outputProfileOptions ?? null) ? $outputProfileOptions : [];
$mappingPretty = $mappingConfig !== []
    ? json_encode($mappingConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : '';
$isMockInterface = str_starts_with(strtoupper((string) ($interface['SystemCode'] ?? '')), 'MOCK_')
    || str_starts_with(strtolower((string) ($interface['BaseUrl'] ?? '')), 'local://');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-play-circle me-2"></i>Test Export Runner</h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>


      <div class="alert <?= $isMockInterface ? 'alert-warning' : 'alert-info' ?>">
        <div class="fw-semibold mb-1">
          <?= $isMockInterface ? 'Mock Run - No External Dispatch' : 'Safe Preview - No External Dispatch' ?>
        </div>
        <div>
          This runner reads from the configured source object, maps the records, builds the outbound JSON preview, and logs the run. It does not call an external endpoint.
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 flex-wrap mb-3">
        <a href="index.php?route=integration-admin/interface-form&id=<?= (int) ($interface['IntegrationInterfaceID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square me-1"></i>Edit Interface</a>
        <a href="index.php?route=integration-admin/interfaces" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="text-muted">Interface</div>
            <div class="fw-semibold"><?= h((string) ($interface['InterfaceName'] ?? '')) ?></div>
            <div class="text-muted"><?= h((string) ($interface['InterfaceCode'] ?? '')) ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="text-muted">System</div>
            <div class="fw-semibold"><?= h((string) ($interface['SystemName'] ?? '')) ?></div>
            <div class="text-muted"><?= h((string) ($interface['SystemCode'] ?? '')) ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="text-muted">Source Object</div>
            <div class="fw-semibold"><?= h((string) ($mappingConfig['source_object'] ?? 'Not configured')) ?></div>
            <div class="text-muted"><?= h((string) ($mappingConfig['source_type'] ?? '')) ?></div>
          </div>
        </div>
      </div>

      <form method="post" action="index.php?route=integration-admin/run-test-export" class="row g-3 mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="IntegrationInterfaceID" value="<?= (int) ($interface['IntegrationInterfaceID'] ?? 0) ?>">

        <div class="col-md-3">
          <label class="form-label">Fiscal Year ID</label>
          <input type="number" min="0" step="1" name="FiscalYearID" class="form-control" value="<?= h((string) ($formData['FiscalYearID'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Version ID</label>
          <input type="number" min="0" step="1" name="VersionID" class="form-control" value="<?= h((string) ($formData['VersionID'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">DataObjectCode</label>
          <input type="text" name="DataObjectCode" class="form-control" value="<?= h((string) ($formData['DataObjectCode'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Preview Row Limit</label>
          <input type="number" min="1" max="5000" step="1" name="PreviewLimit" class="form-control" value="<?= h((string) ($formData['PreviewLimit'] ?? '200')) ?>">
        </div>

        <?php if ($outputProfileOptions !== []): ?>
          <div class="col-md-6">
            <label class="form-label">Output Profile</label>
            <select name="OutputProfileCode" class="form-select">
              <option value="">Default / first configured profile</option>
              <?php foreach ($outputProfileOptions as $profile): ?>
                <?php $profileCode = trim((string) ($profile['code'] ?? '')); ?>
                <?php if ($profileCode === '') { continue; } ?>
                <option value="<?= h($profileCode) ?>" <?= ((string) ($formData['OutputProfileCode'] ?? '') === $profileCode) ? 'selected' : '' ?>>
                  <?= h((string) (($profile['label'] ?? $profileCode) . ' [' . $profileCode . ']')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="col-12">
          <div class="small text-muted">
            Required by interface:
            FY <?= ((int) ($interface['FiscalYearRequiredFlag'] ?? 0) === 1) ? 'Yes' : 'No' ?>,
            Version <?= ((int) ($interface['VersionRequiredFlag'] ?? 0) === 1) ? 'Yes' : 'No' ?>,
            Scope <?= ((int) ($interface['DataScopeRequiredFlag'] ?? 0) === 1) ? 'Yes' : 'No' ?>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">
          <button type="submit" class="btn btn-primary"><i class="bi bi-play-circle me-1"></i>Run Test Export</button>
          <?php if ($isMockInterface): ?>
            <button type="submit" class="btn btn-outline-primary" formaction="index.php?route=integration-admin/dispatch-test-export">
              <i class="bi bi-send-check me-1"></i>Dispatch to Mock API
            </button>
          <?php endif; ?>
        </div>
      </form>

      <div class="row g-3 mb-4">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header">Mapping Configuration</div>
            <div class="card-body">
              <textarea class="form-control font-monospace" rows="16" readonly><?= h((string) $mappingPretty) ?></textarea>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header">Recent Test Runs</div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Started</th>
                      <th>Status</th>
                      <th>Records</th>
                      <th>Summary</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($recentRuns === []): ?>
                      <tr><td colspan="4" class="text-center text-muted py-3">No runs recorded yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($recentRuns as $run): ?>
                        <?php
                        $status = strtolower((string) ($run['RunStatusCode'] ?? ''));
                        $statusClass = match ($status) {
                            'success' => 'text-bg-success',
                            'warning' => 'text-bg-warning',
                            'failed' => 'text-bg-danger',
                            'running' => 'text-bg-primary',
                            default => 'text-bg-light border',
                        };
                        ?>
                        <tr>
                          <td><?= h((string) ($run['StartedAt'] ?? '')) ?></td>
                          <td><span class="badge <?= h($statusClass) ?>"><?= h((string) ($run['RunStatusCode'] ?? '')) ?></span></td>
                          <td><?= (int) ($run['RecordsProcessed'] ?? 0) ?></td>
                          <td>
                            <div><?= h((string) ($run['SummaryText'] ?? '')) ?></div>
                            <?php if (in_array((string) ($run['TriggerSourceCode'] ?? ''), ['manual_preview', 'mock_api_dispatch'], true)): ?>
                              <div class="small mt-1"><span class="badge text-bg-warning">No external dispatch</span></div>
                            <?php endif; ?>
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
      </div>

      <?php if ($previewResult !== null): ?>
        <?php
        $status = strtolower((string) ($previewResult['status'] ?? 'warning'));
        $statusClass = match ($status) {
            'success' => 'success',
            'warning' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
        ?>
        <div class="alert alert-<?= h($statusClass) ?>">
          <div class="fw-semibold mb-1">Run <?= h((string) ($previewResult['run_id'] ?? '')) ?>: <?= h((string) strtoupper($status)) ?></div>
          <div><?= h((string) ($previewResult['summary'] ?? '')) ?></div>
          <div class="mt-2"><span class="badge text-bg-warning">Mock / preview only - no external dispatch</span></div>
        </div>

        <?php if ($dispatchResult !== null): ?>
          <?php
          $dispatchStatus = strtolower((string) ($dispatchResult['status'] ?? 'warning'));
          $dispatchClass = match ($dispatchStatus) {
              'success' => 'success',
              'warning' => 'warning',
              'failed' => 'danger',
              default => 'secondary',
          };
          ?>
          <div class="alert alert-<?= h($dispatchClass) ?>">
            <div class="fw-semibold mb-1">Mock API Dispatch <?= h((string) strtoupper($dispatchStatus)) ?></div>
            <div><?= h((string) ($dispatchResult['summary'] ?? '')) ?></div>
            <?php if (!empty($dispatchResult['correlation_id'])): ?>
              <div class="small mt-1">Mock correlation ID: <code><?= h((string) $dispatchResult['correlation_id']) ?></code></div>
            <?php endif; ?>
          </div>

          <div class="card mb-4">
            <div class="card-header">Mock API Response</div>
            <div class="card-body">
              <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Records Sent</div><div class="fs-4 fw-semibold"><?= (int) ($dispatchResult['record_count'] ?? 0) ?></div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Accepted</div><div class="fs-4 fw-semibold"><?= (int) ($dispatchResult['accepted_count'] ?? 0) ?></div></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted">Failed</div><div class="fs-4 fw-semibold"><?= (int) ($dispatchResult['failed_count'] ?? 0) ?></div></div></div>
              </div>
              <textarea class="form-control font-monospace" rows="12" readonly><?= h((string) ($dispatchResult['response_json'] ?? '')) ?></textarea>
            </div>
          </div>
        <?php endif; ?>

        <div class="d-flex justify-content-end gap-2 flex-wrap mb-3">
          <form method="post" action="index.php?route=integration-admin/download-test-export">
            <?= csrf_field() ?>
            <input type="hidden" name="IntegrationInterfaceID" value="<?= (int) ($interface['IntegrationInterfaceID'] ?? 0) ?>">
            <input type="hidden" name="FiscalYearID" value="<?= h((string) ($formData['FiscalYearID'] ?? '')) ?>">
            <input type="hidden" name="VersionID" value="<?= h((string) ($formData['VersionID'] ?? '')) ?>">
            <input type="hidden" name="DataObjectCode" value="<?= h((string) ($formData['DataObjectCode'] ?? '')) ?>">
            <input type="hidden" name="PreviewLimit" value="<?= h((string) ($formData['PreviewLimit'] ?? '')) ?>">
            <input type="hidden" name="OutputProfileCode" value="<?= h((string) ($formData['OutputProfileCode'] ?? '')) ?>">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>Download Full JSON</button>
          </form>
          <form method="post" action="index.php?route=integration-admin/download-test-export-csv">
            <?= csrf_field() ?>
            <input type="hidden" name="IntegrationInterfaceID" value="<?= (int) ($interface['IntegrationInterfaceID'] ?? 0) ?>">
            <input type="hidden" name="FiscalYearID" value="<?= h((string) ($formData['FiscalYearID'] ?? '')) ?>">
            <input type="hidden" name="VersionID" value="<?= h((string) ($formData['VersionID'] ?? '')) ?>">
            <input type="hidden" name="DataObjectCode" value="<?= h((string) ($formData['DataObjectCode'] ?? '')) ?>">
            <input type="hidden" name="PreviewLimit" value="<?= h((string) ($formData['PreviewLimit'] ?? '')) ?>">
            <input type="hidden" name="OutputProfileCode" value="<?= h((string) ($formData['OutputProfileCode'] ?? '')) ?>">
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i>Download Full CSV</button>
          </form>
          <form method="post" action="index.php?route=integration-admin/download-test-export-package">
            <?= csrf_field() ?>
            <input type="hidden" name="IntegrationInterfaceID" value="<?= (int) ($interface['IntegrationInterfaceID'] ?? 0) ?>">
            <input type="hidden" name="FiscalYearID" value="<?= h((string) ($formData['FiscalYearID'] ?? '')) ?>">
            <input type="hidden" name="VersionID" value="<?= h((string) ($formData['VersionID'] ?? '')) ?>">
            <input type="hidden" name="DataObjectCode" value="<?= h((string) ($formData['DataObjectCode'] ?? '')) ?>">
            <input type="hidden" name="PreviewLimit" value="<?= h((string) ($formData['PreviewLimit'] ?? '')) ?>">
            <input type="hidden" name="OutputProfileCode" value="<?= h((string) ($formData['OutputProfileCode'] ?? '')) ?>">
            <button type="submit" class="btn btn-outline-dark"><i class="bi bi-file-zip me-1"></i>Download Review Package</button>
          </form>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted">Source Rows</div>
              <div class="fs-4 fw-semibold"><?= (int) ($previewResult['source_row_count'] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted">Mapped Records</div>
              <div class="fs-4 fw-semibold"><?= (int) ($previewResult['mapped_record_count'] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted">Preview Limit</div>
              <div class="fs-4 fw-semibold"><?= (int) ($previewResult['preview_limit'] ?? 0) ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted">Row Limit Reached</div>
              <div class="fs-4 fw-semibold"><?= !empty($previewResult['truncated']) ? 'Yes' : 'No' ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="border rounded p-3 h-100">
              <div class="small text-muted">External Dispatch</div>
              <div class="fs-4 fw-semibold">No</div>
            </div>
          </div>
        </div>

        <?php if (!empty($previewResult['resolved_scope_codes']) && is_array($previewResult['resolved_scope_codes'])): ?>
          <div class="alert alert-secondary">
            <div class="fw-semibold mb-1">Resolved Scope Filter</div>
            <div class="small">
              <?= h((string) count($previewResult['resolved_scope_codes'])) ?> scope code(s) matched from the selected value
              <code><?= h((string) ($formData['DataObjectCode'] ?? '')) ?></code>.
            </div>
            <div class="small mt-1">
              <?= h(implode(', ', array_slice(array_map('strval', $previewResult['resolved_scope_codes']), 0, 25))) ?>
              <?php if (count($previewResult['resolved_scope_codes']) > 25): ?>
                ... and <?= h((string) (count($previewResult['resolved_scope_codes']) - 25)) ?> more
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($previewResult['selected_profile']) && is_array($previewResult['selected_profile'])): ?>
          <div class="alert alert-secondary">
            <div class="fw-semibold mb-1">Output Profile Applied</div>
            <div class="small">
              <?= h((string) ($previewResult['selected_profile']['label'] ?? $previewResult['selected_profile']['code'] ?? '')) ?>
              <?php if (!empty($previewResult['selected_profile']['description'])): ?>
                - <?= h((string) $previewResult['selected_profile']['description']) ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="card mb-4">
          <div class="card-header">Outbound Payload Preview</div>
          <div class="card-body">
            <textarea class="form-control font-monospace" rows="18" readonly><?= h((string) ($previewResult['payload_json'] ?? '')) ?></textarea>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header">Sample Source Rows</div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <?php if (!empty($previewResult['source_rows'][0]) && is_array($previewResult['source_rows'][0])): ?>
                          <?php foreach (array_keys($previewResult['source_rows'][0]) as $column): ?>
                            <th><?= h((string) $column) ?></th>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <th>Source Rows</th>
                        <?php endif; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($previewResult['source_rows'])): ?>
                        <tr><td class="text-center text-muted py-3">No source rows returned.</td></tr>
                      <?php else: ?>
                        <?php foreach ($previewResult['source_rows'] as $row): ?>
                          <tr>
                            <?php foreach ((array) $row as $value): ?>
                              <td><?= h((string) $value) ?></td>
                            <?php endforeach; ?>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header">Sample Mapped Records</div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <?php if (!empty($previewResult['mapped_records'][0]) && is_array($previewResult['mapped_records'][0])): ?>
                          <?php foreach (array_keys($previewResult['mapped_records'][0]) as $column): ?>
                            <th><?= h((string) $column) ?></th>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <th>Mapped Records</th>
                        <?php endif; ?>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($previewResult['mapped_records'])): ?>
                        <tr><td class="text-center text-muted py-3">No mapped records were generated.</td></tr>
                      <?php else: ?>
                        <?php foreach ($previewResult['mapped_records'] as $row): ?>
                          <tr>
                            <?php foreach ((array) $row as $value): ?>
                              <td><?= h((string) $value) ?></td>
                            <?php endforeach; ?>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
