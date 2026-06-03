<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Fiscal Risks</h3>
      <a href="index.php?route=strategy-governance/fiscal-risk-form" class="btn btn-sm btn-primary">Create Fiscal Risk</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Review and maintain fiscal risk records for the active strategic context.</div>

      <form method="get" class="row g-2 mb-3">
        <input type="hidden" name="route" value="strategy-governance/fiscal-risks">
        <div class="col-md-5"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search risks" value="<?= h((string) ($q ?? '')) ?>"></div>
        <div class="col-md-3">
          <select name="risk_type_code" class="form-select form-select-sm">
            <option value="">All risk types</option>
            <?php foreach (($riskTypeOptions ?? []) as $code => $label): ?>
              <option value="<?= h((string) $code) ?>" <?= ((string) ($riskTypeCode ?? '') === (string) $code) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
          <a href="index.php?route=strategy-governance/fiscal-risks" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Type</th>
              <th>Title</th>
              <th class="text-end">Likelihood</th>
              <th class="text-end">Impact</th>
              <th class="text-end">Exposure</th>
              <th>Owner</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (($records ?? []) === []): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No fiscal risks yet.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <tr>
                <td><?= h((string) ($row['RiskTypeCode'] ?? '')) ?></td>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['RiskTitle'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h(mb_strimwidth((string) ($row['RiskDescription'] ?? ''), 0, 100, '...')) ?></div>
                </td>
                <td class="text-end"><?= h((string) ($row['LikelihoodScore'] ?? '')) ?></td>
                <td class="text-end"><?= h((string) ($row['ImpactScore'] ?? '')) ?></td>
                <td class="text-end"><?= number_format((float) ($row['EstimatedFiscalExposure'] ?? 0), 2) ?></td>
                <td>
                  <?= h((string) ($row['OwnerOrgUnitName'] ?? '')) ?>
                  <?php if (!empty($row['ProjectName'])): ?>
                    <div class="small text-muted"><?= h((string) (($row['ProjectCode'] ?? '') !== '' ? $row['ProjectCode'] . ' / ' : '') . $row['ProjectName']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a href="index.php?route=strategy-governance/fiscal-risk-form&id=<?= (int) $row['FiscalRiskID'] ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                  <form method="post" action="index.php?route=strategy-governance/delete-fiscal-risk" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['FiscalRiskID'] ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Archive</button>
                  </form>
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
