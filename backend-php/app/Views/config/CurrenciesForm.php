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
$currencyCode = trim((string) ($record['CurrencyCode'] ?? ''));
$isEditing = $currencyCode !== '';
$screenHeader = [
    'title' => $isEditing ? 'Edit Currency' : 'Create Currency',
    'icon' => 'bi-currency-dollar',
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
        Use this form to create or refine one currency in the shared master list. Keep the code aligned with the ISO alpha-3 style used elsewhere in CBMS, then set the default and active flags carefully.
      </div>

      <form method="post" action="index.php?route=currencies/save" id="currencies-form">
        <?= csrf_field() ?>
        <input type="hidden" name="_editing" value="<?= $isEditing ? '1' : '0' ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Identity</h5>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label">Currency Code</label>
                <input id="currencyCode" class="form-control" type="text" name="CurrencyCode" maxlength="3" value="<?= h($currencyCode) ?>" <?= $isEditing ? 'readonly' : 'required' ?>>
                <div class="form-text">Use a 3-letter uppercase code such as <code>LSL</code> or <code>USD</code>.</div>
              </div>
              <div class="col-md-8">
                <label class="form-label">Currency Name</label>
                <input id="currencyName" class="form-control" type="text" name="CurrencyName" value="<?= h((string) ($record['CurrencyName'] ?? '')) ?>" required>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Currency Symbol</label>
                <input class="form-control" type="text" name="CurrencySymbol" maxlength="10" value="<?= h((string) ($record['CurrencySymbol'] ?? '')) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">ISO Numeric Code</label>
                <input class="form-control" type="text" name="IsoNumericCode" maxlength="3" value="<?= h((string) ($record['IsoNumericCode'] ?? '')) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Sort Order</label>
                <input class="form-control" type="number" name="SortOrder" value="<?= h((string) ($record['SortOrder'] ?? '100')) ?>" min="0">
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Formatting And Status</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Decimal Places</label>
              <input id="currencyDecimalPlaces" class="form-control" type="number" name="DecimalPlaces" min="0" max="6" value="<?= h((string) ($record['DecimalPlaces'] ?? '2')) ?>">
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="currencyIsActive" name="IsActive" <?= ((int) ($record['IsActive'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="currencyIsActive">Active currency</label>
            </div>

            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="currencyIsSystemDefault" name="IsSystemDefault" <?= ((int) ($record['IsSystemDefault'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="currencyIsSystemDefault">System default currency</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a id="currencies-back-btn" href="index.php?route=currencies/list" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="currencies-save-btn" type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
