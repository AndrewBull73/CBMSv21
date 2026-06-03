<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money_fmt')) {
    function money_fmt(mixed $value): string
    {
        return number_format((float) $value, 2);
    }
}

$csrf = h(csrf_token());
?>
<div class="container mt-4">
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Strategic Activity Budgets</h3>
      <a href="index.php?route=strategy-delivery/budget-form" class="btn btn-sm btn-primary">Create Budget Line</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Context:
        <strong><?= h((string) ($contextLabels['YearLabel'] ?? '')) ?></strong>
        /
        <strong><?= h((string) ($contextLabels['VersionLabel'] ?? '')) ?></strong>
      </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">Budget Lines</div><div class="fs-4 fw-semibold"><?= (int) ($summary['BudgetLineCount'] ?? 0) ?></div></div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">Budgeted Activities</div><div class="fs-4 fw-semibold"><?= (int) ($summary['ActivityCount'] ?? 0) ?></div></div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">Total Amount</div><div class="fs-4 fw-semibold"><?= money_fmt($summary['TotalAmount'] ?? 0) ?></div></div></div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="strategy-delivery/budgets">
        <div class="col-md-5">
          <input type="text" name="q" value="<?= h((string) ($q ?? '')) ?>" class="form-control form-control-sm" placeholder="Search budget lines">
        </div>
        <div class="col-md-5">
          <select name="activity_id" class="form-select form-select-sm">
            <option value="">All activities</option>
            <?php foreach (($activityOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['ActivityID'] ?>" <?= ((int) ($activityId ?? 0) === (int) $option['ActivityID']) ? 'selected' : '' ?>>
                <?= h((string) $option['ProgramName'] . ' / ' . $option['ActivityName']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Program / Activity</th>
              <th>Economic Item</th>
              <th>Funding</th>
              <th class="text-end">Amount</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No budget lines found for the active fiscal context.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h((string) (($row['OutputName'] ?? '') . ' / ' . ($row['ActivityName'] ?? ''))) ?></div>
                </td>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['EconomicName'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h((string) ($row['EconomicCode'] ?? '')) ?></div>
                </td>
                <td><?= h((string) ($row['FundingSourceName'] ?? '')) ?></td>
                <td class="text-end"><?= money_fmt($row['Amount'] ?? 0) ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a href="index.php?route=strategy-delivery/budget-form&id=<?= (int) ($row['ActivityBudgetID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                    <form method="post" action="index.php?route=strategy-delivery/delete-budget" onsubmit="return confirm('Archive this budget line?');">
                      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="id" value="<?= (int) ($row['ActivityBudgetID'] ?? 0) ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                    </form>
                  </div>
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
</div>
