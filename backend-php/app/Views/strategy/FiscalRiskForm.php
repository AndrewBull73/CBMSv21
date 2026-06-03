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
$id = (int) ($record['FiscalRiskID'] ?? 0);
$supportsFiscalRiskProjectLink = (bool) ($supportsFiscalRiskProjectLink ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i><?= $id > 0 ? 'Edit Fiscal Risk' : 'Create Fiscal Risk' ?></h3>
      <a href="index.php?route=strategy-governance/fiscal-risks" class="btn btn-sm btn-outline-secondary">Back to Fiscal Risks</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Use this form to record one fiscal risk, including risk scoring, ownership, exposure, and mitigation details.</div>

      <form method="post" action="index.php?route=strategy-governance/save-fiscal-risk" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="FiscalRiskID" value="<?= $id ?>">
        <div class="col-md-4">
          <label class="form-label">Risk Type</label>
          <select name="RiskTypeCode" class="form-select form-select-sm" required>
            <option value="">Select type</option>
            <?php foreach (($riskTypeOptions ?? []) as $code => $label): ?>
              <option value="<?= h((string) $code) ?>" <?= ((string) ($record['RiskTypeCode'] ?? '') === (string) $code) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label">Title</label>
          <input type="text" name="RiskTitle" class="form-control form-control-sm" required value="<?= h((string) ($record['RiskTitle'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Likelihood (1-5)</label>
          <input type="number" min="1" max="5" name="LikelihoodScore" class="form-control form-control-sm" value="<?= h((string) ($record['LikelihoodScore'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Impact (1-5)</label>
          <input type="number" min="1" max="5" name="ImpactScore" class="form-control form-control-sm" value="<?= h((string) ($record['ImpactScore'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Estimated Exposure</label>
          <input type="number" step="0.01" name="EstimatedFiscalExposure" class="form-control form-control-sm" value="<?= h((string) ($record['EstimatedFiscalExposure'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Owner Org Unit</label>
          <select name="OwnerOrgUnitID" class="form-select form-select-sm">
            <option value="">None</option>
            <?php foreach (($orgUnitOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['OrgUnitID'] ?>" <?= ((int) ($record['OwnerOrgUnitID'] ?? 0) === (int) $option['OrgUnitID']) ? 'selected' : '' ?>><?= h((string) $option['OrgUnitName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Linked Project</label>
          <?php if ($supportsFiscalRiskProjectLink): ?>
            <select name="ProjectID" class="form-select form-select-sm">
              <option value="">None</option>
              <?php foreach (($projectOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['ProjectID'] ?>" <?= ((int) ($record['ProjectID'] ?? 0) === (int) $option['ProjectID']) ? 'selected' : '' ?>>
                  <?= h((string) (($option['ProjectCode'] ?? '') !== '' ? $option['ProjectCode'] . ' / ' : '') . ($option['ProjectName'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <div class="alert alert-warning py-2 mb-0">Run <code>alter_tblSbFiscalRisk_add_project.sql</code> to enable project links.</div>
          <?php endif; ?>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="RiskDescription" class="form-control form-control-sm" rows="4"><?= h((string) ($record['RiskDescription'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Mitigation Strategy</label>
          <textarea name="MitigationStrategy" class="form-control form-control-sm" rows="4"><?= h((string) ($record['MitigationStrategy'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ActiveFlag" id="RiskActiveFlag" <?= !isset($record['ActiveFlag']) || (int) ($record['ActiveFlag'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="RiskActiveFlag">Active</label>
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary btn-sm" type="submit">Save Fiscal Risk</button>
          <a href="index.php?route=strategy-governance/fiscal-risks" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
