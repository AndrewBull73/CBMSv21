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
$systemOptions = is_array($systemOptions ?? null) ? $systemOptions : [];
$directionOptions = is_array($directionOptions ?? null) ? $directionOptions : [];
$triggerModeOptions = is_array($triggerModeOptions ?? null) ? $triggerModeOptions : [];
$httpMethodOptions = is_array($httpMethodOptions ?? null) ? $httpMethodOptions : [];
$payloadFormatOptions = is_array($payloadFormatOptions ?? null) ? $payloadFormatOptions : [];
$approvalStageOptions = is_array($approvalStageOptions ?? null) ? $approvalStageOptions : [];
$readinessStatusOptions = is_array($readinessStatusOptions ?? null) ? $readinessStatusOptions : [];
$outputProfileOptions = is_array($outputProfileOptions ?? null) ? $outputProfileOptions : [];
$id = (int) ($record['IntegrationInterfaceID'] ?? 0);
$directionCode = strtolower(trim((string) ($record['DirectionCode'] ?? '')));
$canTestExport = in_array($directionCode, ['outbound', 'bidirectional'], true);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i><?= $id > 0 ? 'Edit Integration Interface' : 'Create Integration Interface' ?></h3>
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
          Run <code><?= h($installScriptPath) ?></code> before creating integration interfaces.
        </div>
      <?php elseif ($systemOptions === []): ?>
        <div class="alert alert-warning mb-0">
          Create at least one integration system first, then come back here to register interfaces against it.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Capture one clear contract per data flow so import/export behavior can be configured independently.
        </div>

        <?php if ($id > 0 && $canTestExport): ?>
          <div class="d-flex justify-content-end mb-3">
            <a href="index.php?route=integration-admin/test-export&id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-play-circle me-1"></i>Test Export</a>
          </div>
        <?php endif; ?>

        <form method="post" action="index.php?route=integration-admin/save-interface">
          <?= csrf_field() ?>
          <input type="hidden" name="IntegrationInterfaceID" value="<?= $id ?>">

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Interface Code</label>
              <input type="text" name="InterfaceCode" class="form-control" value="<?= h((string) ($record['InterfaceCode'] ?? '')) ?>" required placeholder="ACTUALS_IMPORT_A">
            </div>
            <div class="col-md-5">
              <label class="form-label">Interface Name</label>
              <input type="text" name="InterfaceName" class="form-control" value="<?= h((string) ($record['InterfaceName'] ?? '')) ?>" required placeholder="Import Daily Actuals from Finance A">
            </div>
            <div class="col-md-3">
              <label class="form-label">Direction</label>
              <select name="DirectionCode" class="form-select" required>
                <?php foreach ($directionOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['DirectionCode'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">System</label>
              <select name="IntegrationSystemID" class="form-select" required>
                <option value="">Select system</option>
                <?php foreach ($systemOptions as $option): ?>
                  <option value="<?= (int) ($option['IntegrationSystemID'] ?? 0) ?>" <?= ((string) ($record['IntegrationSystemID'] ?? '') === (string) ($option['IntegrationSystemID'] ?? '')) ? 'selected' : '' ?>>
                    <?= h((string) (($option['SystemName'] ?? '') . ' [' . ($option['SystemCode'] ?? '') . ']')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Module Code</label>
              <input type="text" name="ModuleCode" class="form-control" value="<?= h((string) ($record['ModuleCode'] ?? '')) ?>" placeholder="BUDGET_SUBMISSION">
            </div>
            <div class="col-md-4">
              <label class="form-label">Entity Code</label>
              <input type="text" name="EntityCode" class="form-control" value="<?= h((string) ($record['EntityCode'] ?? '')) ?>" placeholder="ACTUALS">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Trigger Mode</label>
              <select name="TriggerMode" class="form-select">
                <?php foreach ($triggerModeOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['TriggerMode'] ?? 'manual') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Schedule Expression</label>
              <input type="text" name="ScheduleExpression" class="form-control" value="<?= h((string) ($record['ScheduleExpression'] ?? '')) ?>" placeholder="0 1 * * *">
            </div>
            <div class="col-md-4">
              <label class="form-label">Payload Format</label>
              <select name="PayloadFormat" class="form-select">
                <?php foreach ($payloadFormatOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['PayloadFormat'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label">Endpoint Path</label>
              <input type="text" name="EndpointPath" class="form-control" value="<?= h((string) ($record['EndpointPath'] ?? '')) ?>" placeholder="/api/v1/actuals/daily">
            </div>
            <div class="col-md-2">
              <label class="form-label">HTTP Method</label>
              <select name="HttpMethod" class="form-select">
                <?php foreach ($httpMethodOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['HttpMethod'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1">
              <label class="form-label">Batch</label>
              <input type="number" min="0" step="1" name="BatchSize" class="form-control" value="<?= h((string) ($record['BatchSize'] ?? '')) ?>" placeholder="500">
            </div>
            <div class="col-md-1">
              <label class="form-label">Timeout</label>
              <input type="number" min="0" step="1" name="TimeoutSeconds" class="form-control" value="<?= h((string) ($record['TimeoutSeconds'] ?? '')) ?>" placeholder="60">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label">Business Owner</label>
              <input type="text" name="BusinessOwner" class="form-control" value="<?= h((string) ($record['BusinessOwner'] ?? '')) ?>" placeholder="Budget Director">
            </div>
            <div class="col-md-3">
              <label class="form-label">Source Owner</label>
              <input type="text" name="SourceOwner" class="form-control" value="<?= h((string) ($record['SourceOwner'] ?? '')) ?>" placeholder="Finance Data Team">
            </div>
            <div class="col-md-3">
              <label class="form-label">Approval Stage</label>
              <select name="ApprovalStage" class="form-select">
                <?php foreach ($approvalStageOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['ApprovalStage'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Readiness Status</label>
              <select name="ReadinessStatus" class="form-select">
                <?php foreach ($readinessStatusOptions as $value => $label): ?>
                  <option value="<?= h((string) $value) ?>" <?= ((string) ($record['ReadinessStatus'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Required Context</label>
            <div class="row g-2">
              <?php
              $flags = [
                  'ContextRequiredFlag' => 'Any linked screen context required',
                  'FiscalYearRequiredFlag' => 'Fiscal year required',
                  'VersionRequiredFlag' => 'Version required',
                  'DataScopeRequiredFlag' => 'Data scope required',
              ];
              ?>
              <?php foreach ($flags as $field => $label): ?>
                <div class="col-md-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= h($field) ?>" id="<?= h($field) ?>" value="1" <?= ((int) ($record[$field] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= h($field) ?>"><?= h($label) ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Mapping Configuration JSON</label>
            <textarea name="MappingConfigJson" class="form-control font-monospace" rows="7" placeholder='{"sourceKeys":["fund","program","economic_item"],"targetEntity":"actuals_staging"}'><?= h((string) ($record['MappingConfigJson'] ?? '')) ?></textarea>
            <div class="form-text">Optional adapter or transformation hints for the future import/export engine.</div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label">Output Profiles JSON</label>
              <textarea name="OutputProfilesJson" class="form-control font-monospace" rows="7" placeholder='[{"code":"finance_json_v1","label":"Finance JSON v1","rename_map":{"bp_total":"annual_total"},"records_key":"lines"}]'><?= h((string) ($record['OutputProfilesJson'] ?? '')) ?></textarea>
              <div class="form-text">Optional export templates. Use one JSON object per profile with at least a <code>code</code>. Supported keys include <code>label</code>, <code>description</code>, <code>include_fields</code>, <code>exclude_fields</code>, <code>rename_map</code>, <code>field_order</code>, and <code>records_key</code>.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Default Output Profile</label>
              <?php if ($outputProfileOptions !== []): ?>
                <select name="DefaultOutputProfileCode" class="form-select">
                  <option value="">No default profile</option>
                  <?php foreach ($outputProfileOptions as $profile): ?>
                    <?php $profileCode = trim((string) ($profile['code'] ?? '')); ?>
                    <?php if ($profileCode === '') { continue; } ?>
                    <option value="<?= h($profileCode) ?>" <?= ((string) ($record['DefaultOutputProfileCode'] ?? '') === $profileCode) ? 'selected' : '' ?>>
                      <?= h((string) (($profile['label'] ?? $profileCode) . ' [' . $profileCode . ']')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="text" name="DefaultOutputProfileCode" class="form-control" value="<?= h((string) ($record['DefaultOutputProfileCode'] ?? '')) ?>" placeholder="finance_json_v1">
              <?php endif; ?>
              <div class="form-text">Set a default profile code to preselect a payload template in the test runner.</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="Notes" class="form-control" rows="4" placeholder="Document business ownership, approval rules, staging expectations, or exception handling notes."><?= h((string) ($record['Notes'] ?? '')) ?></textarea>
          </div>

          <div class="form-check mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ActiveFlag">Interface is active and available for future execution</label>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <a href="index.php?route=integration-admin/interfaces" class="btn btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Interface</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
