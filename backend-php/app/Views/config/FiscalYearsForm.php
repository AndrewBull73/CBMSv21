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
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$id = (int) ($record['FiscalYearID'] ?? 0);
$screenHeader = [
    'title' => $id > 0 ? 'Edit Fiscal Year' : 'Create Fiscal Year',
    'icon' => 'bi-calendar3',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this form to create or refine one fiscal year record. Confirm the client year label and full date range first, then decide whether this year should remain active and whether it should become the system default context year.
      </div>

      <form method="post" action="index.php?route=fiscal-years/save" id="fiscal-years-form">
        <?= csrf_field() ?>
        <?php if ($id > 0): ?>
          <input type="hidden" name="OriginalFiscalYearID" value="<?= $id ?>">
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Identity</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Fiscal Year ID</label>
              <input id="fiscalYearId" class="form-control" type="number" min="1" name="FiscalYearID" value="<?= h((string) ($record['FiscalYearID'] ?? '')) ?>" <?= $id > 0 ? 'readonly' : 'required' ?>>
              <div class="form-text">Use the client fiscal year number, for example <code>2026</code>.</div>
            </div>

            <div class="mb-0">
              <label class="form-label">Year Label</label>
              <input id="fiscalYearLabel" class="form-control" type="text" name="YearLabel" value="<?= h((string) ($record['YearLabel'] ?? '')) ?>" required>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Dates</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Start Date</label>
                <input id="fiscalYearStartDate" class="form-control" type="date" name="StartDate" value="<?= h(substr((string) ($record['StartDate'] ?? ''), 0, 10)) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">End Date</label>
                <input id="fiscalYearEndDate" class="form-control" type="date" name="EndDate" value="<?= h(substr((string) ($record['EndDate'] ?? ''), 0, 10)) ?>" required>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Status</h5>
          </div>
          <div class="card-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="fiscalYearIsActive" name="IsActive" <?= ((int) ($record['IsActive'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="fiscalYearIsActive">Active fiscal year</label>
            </div>

            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="fiscalYearIsSystemDefault" name="IsSystemDefault" <?= ((int) ($record['IsSystemDefault'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="fiscalYearIsSystemDefault">Set as system default fiscal year</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a id="fiscal-years-back-btn" href="index.php?route=fiscal-years/list" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="fiscal-years-save-btn" type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
