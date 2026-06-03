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
$id = (int) ($record['ProgramRiskID'] ?? 0);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-link-45deg me-2"></i><?= $id > 0 ? 'Edit Program Risk Link' : 'Create Program Risk Link' ?></h3>
      <a href="index.php?route=strategy-governance/program-risks" class="btn btn-sm btn-outline-secondary">Back to Program Risks</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Use this form to connect one fiscal risk to one program for planning and governance review.</div>

      <form method="post" action="index.php?route=strategy-governance/save-program-risk" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="ProgramRiskID" value="<?= $id ?>">
        <div class="col-md-6">
          <label class="form-label">Program</label>
          <select name="ProgramID" class="form-select form-select-sm" required>
            <option value="">Select program</option>
            <?php foreach (($programOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['ProgramID'] ?>" <?= ((int) ($record['ProgramID'] ?? 0) === (int) $option['ProgramID']) ? 'selected' : '' ?>><?= h((string) (($option['ProgramCode'] ?? '') !== '' ? $option['ProgramCode'] . ' / ' : '') . $option['ProgramName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Fiscal Risk</label>
          <select name="FiscalRiskID" class="form-select form-select-sm" required>
            <option value="">Select fiscal risk</option>
            <?php foreach (($fiscalRiskOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['FiscalRiskID'] ?>" <?= ((int) ($record['FiscalRiskID'] ?? 0) === (int) $option['FiscalRiskID']) ? 'selected' : '' ?>><?= h((string) (($option['RiskTypeCode'] ?? '') !== '' ? $option['RiskTypeCode'] . ' / ' : '') . $option['RiskTitle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary btn-sm" type="submit">Save Link</button>
          <a href="index.php?route=strategy-governance/program-risks" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
