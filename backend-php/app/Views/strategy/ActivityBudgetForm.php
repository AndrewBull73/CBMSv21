<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($record['ActivityBudgetID'] ?? 0);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-cash-stack me-2"></i><?= $id > 0 ? 'Edit Activity Budget' : 'Create Activity Budget' ?></h3>
      <a href="index.php?route=strategy-delivery/budgets" class="btn btn-sm btn-outline-secondary">Back to Budgets</a>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Budget context:
        <strong><?= h((string) ($contextLabels['YearLabel'] ?? '')) ?></strong>
        /
        <strong><?= h((string) ($contextLabels['VersionLabel'] ?? '')) ?></strong>
      </div>

      <form method="post" action="index.php?route=strategy-delivery/save-budget">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="ActivityBudgetID" value="<?= $id ?>">

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Activity</label>
            <select name="ActivityID" class="form-select form-select-sm" required>
              <option value="">Select activity</option>
              <?php foreach (($activityOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['ActivityID'] ?>" <?= ((int) ($record['ActivityID'] ?? 0) === (int) $option['ActivityID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['ProgramName'] . ' / ' . $option['OutputName'] . ' / ' . $option['ActivityName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Economic Item</label>
            <select name="EconomicItemID" class="form-select form-select-sm" required>
              <option value="">Select economic item</option>
              <?php foreach (($economicItemOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['EconomicItemID'] ?>" <?= ((int) ($record['EconomicItemID'] ?? 0) === (int) $option['EconomicItemID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['EconomicCode'] . ' / ' . $option['EconomicName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Funding Source</label>
            <select name="FundingSourceID" class="form-select form-select-sm">
              <option value="">None</option>
              <?php foreach (($fundingSourceOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['FundingSourceID'] ?>" <?= ((int) ($record['FundingSourceID'] ?? 0) === (int) $option['FundingSourceID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['FundingSourceName'] . ' (' . $option['FundingTypeCode'] . ')') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" min="0" name="Amount" class="form-control form-control-sm" required value="<?= h((string) ($record['Amount'] ?? '0.00')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Currency Code</label>
            <input type="text" name="CurrencyCode" class="form-control form-control-sm" value="<?= h((string) ($record['CurrencyCode'] ?? '')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="Notes" class="form-control form-control-sm" rows="4"><?= h((string) ($record['Notes'] ?? '')) ?></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="budgetActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="budgetActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-delivery/budgets" class="btn btn-sm btn-outline-secondary">Back</a>
          <button type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
