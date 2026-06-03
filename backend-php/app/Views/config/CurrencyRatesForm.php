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
$currencies = is_array($currencies ?? null) ? $currencies : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$currencyRateId = (int) ($record['CurrencyRateID'] ?? 0);
$isEditing = $currencyRateId > 0;
$screenHeader = [
    'title' => $isEditing ? 'Edit Currency Rate' : 'Create Currency Rate',
    'icon' => 'bi-currency-exchange',
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
        Use this form to maintain one currency exchange rate. Keep the pair direction explicit and use the rate type to distinguish different maintained rate families when needed.
      </div>

      <form method="post" action="index.php?route=currency-rates/save" id="currency-rates-form">
        <?= csrf_field() ?>
        <input type="hidden" name="_editing" value="<?= $isEditing ? '1' : '0' ?>">
        <input type="hidden" name="CurrencyRateID" value="<?= $currencyRateId ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Pair And Date</h5>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">From Currency</label>
                <select id="currencyRateFromCurrencyCode" class="form-select" name="FromCurrencyCode" required>
                  <option value="">Select from currency</option>
                  <?php foreach ($currencies as $currency): ?>
                    <?php $currencyCode = trim((string) ($currency['CurrencyCode'] ?? '')); ?>
                    <option value="<?= h($currencyCode) ?>" <?= (($record['FromCurrencyCode'] ?? '') === $currencyCode) ? 'selected' : '' ?>>
                      <?= h($currencyCode . ' - ' . (string) ($currency['CurrencyName'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">To Currency</label>
                <select id="currencyRateToCurrencyCode" class="form-select" name="ToCurrencyCode" required>
                  <option value="">Select to currency</option>
                  <?php foreach ($currencies as $currency): ?>
                    <?php $currencyCode = trim((string) ($currency['CurrencyCode'] ?? '')); ?>
                    <option value="<?= h($currencyCode) ?>" <?= (($record['ToCurrencyCode'] ?? '') === $currencyCode) ? 'selected' : '' ?>>
                      <?= h($currencyCode . ' - ' . (string) ($currency['CurrencyName'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Rate Date</label>
                <input id="currencyRateDate" class="form-control" type="date" name="RateDate" value="<?= h(substr((string) ($record['RateDate'] ?? ''), 0, 10)) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Rate Type</label>
                <input id="currencyRateType" class="form-control" type="text" name="RateType" value="<?= h((string) ($record['RateType'] ?? 'SPOT')) ?>" required>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Rate Detail</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Rate Value</label>
              <input id="currencyRateValue" class="form-control" type="number" name="RateValue" min="0.00000001" step="0.00000001" value="<?= h((string) ($record['RateValue'] ?? '')) ?>" required>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Rate Source</label>
                <input class="form-control" type="text" name="RateSource" value="<?= h((string) ($record['RateSource'] ?? '')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Notes</label>
                <input class="form-control" type="text" name="Notes" value="<?= h((string) ($record['Notes'] ?? '')) ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Status</h5>
          </div>
          <div class="card-body">
            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="currencyRateIsActive" name="IsActive" <?= ((int) ($record['IsActive'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="currencyRateIsActive">Active rate</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a id="currency-rates-back-btn" href="index.php?route=currency-rates/list" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="currency-rates-save-btn" type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
