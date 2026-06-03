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
      <h3 class="mb-0"><i class="bi bi-link-45deg me-2"></i>Program Risk Links</h3>
      <a href="index.php?route=strategy-governance/program-risk-form" class="btn btn-sm btn-primary">Create Link</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Link fiscal risks to the relevant programs so governance and planning reviews reflect the actual exposure points.</div>

      <form method="get" class="row g-2 mb-3">
        <input type="hidden" name="route" value="strategy-governance/program-risks">
        <div class="col-md-4"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search links" value="<?= h((string) ($q ?? '')) ?>"></div>
        <div class="col-md-3">
          <select name="program_id" class="form-select form-select-sm">
            <option value="">All programs</option>
            <?php foreach (($programOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['ProgramID'] ?>" <?= ((int) ($programId ?? 0) === (int) $option['ProgramID']) ? 'selected' : '' ?>><?= h((string) $option['ProgramName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="fiscal_risk_id" class="form-select form-select-sm">
            <option value="">All fiscal risks</option>
            <?php foreach (($fiscalRiskOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['FiscalRiskID'] ?>" <?= ((int) ($fiscalRiskId ?? 0) === (int) $option['FiscalRiskID']) ? 'selected' : '' ?>><?= h((string) $option['RiskTitle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
          <a href="index.php?route=strategy-governance/program-risks" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Program</th>
              <th>Risk</th>
              <th class="text-end">Likelihood</th>
              <th class="text-end">Impact</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (($records ?? []) === []): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No program risk links yet.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h((string) ($row['ProgramCode'] ?? '')) ?></div>
                </td>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['RiskTitle'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h((string) ($row['RiskTypeCode'] ?? '')) ?></div>
                </td>
                <td class="text-end"><?= h((string) ($row['LikelihoodScore'] ?? '')) ?></td>
                <td class="text-end"><?= h((string) ($row['ImpactScore'] ?? '')) ?></td>
                <td class="text-end">
                  <a href="index.php?route=strategy-governance/program-risk-form&id=<?= (int) $row['ProgramRiskID'] ?>" class="btn btn-outline-secondary btn-sm">Edit</a>
                  <form method="post" action="index.php?route=strategy-governance/delete-program-risk" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['ProgramRiskID'] ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">Remove</button>
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
