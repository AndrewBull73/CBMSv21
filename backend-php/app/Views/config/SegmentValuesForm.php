<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$segments = is_array($segments ?? null) ? $segments : [];
$dataObjectCodes = is_array($dataObjectCodes ?? null) ? $dataObjectCodes : [];
$id = (int) ($record['SegmentValueID'] ?? 0);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Segment Value' : 'Create Segment Value' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=segment-values/save" id="segment-values-form">
        <?= csrf_field() ?>
        <input type="hidden" name="SegmentValueID" value="<?= $id ?>">

        <div class="mb-3">
          <label class="form-label">Fiscal Year</label>
          <select id="segmentValueFiscalYearID" name="FiscalYearID" class="form-select" required>
            <option value="">Select</option>
            <?php foreach ($fiscalYears as $fy): ?>
              <?php $fyId = (string) ($fy['FiscalYearID'] ?? ''); ?>
              <option value="<?= h($fyId) ?>" <?= ((string) ($record['FiscalYearID'] ?? ($ctxFy > 0 ? (string) $ctxFy : '')) === $fyId) ? 'selected' : '' ?>>
                <?= h($fyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Org Unit Code</label>
          <input id="segmentValueDataObjectCode" class="form-control" list="segmentValueDataObjects" name="DataObjectCode" value="<?= h((string) ($record['DataObjectCode'] ?? '')) ?>" required>
          <datalist id="segmentValueDataObjects">
            <?php foreach ($dataObjectCodes as $doc): ?>
              <option value="<?= h((string) ($doc['DataObjectCode'] ?? '')) ?>"><?= h((string) ($doc['DataObjectName'] ?? '')) ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="mb-3">
          <label class="form-label">Segment</label>
          <select id="segmentValueSegmentNo" name="SegmentNo" class="form-select" required>
            <option value="">Select</option>
            <?php foreach ($segments as $segment): ?>
              <?php $segmentNo = (string) ($segment['SegmentNo'] ?? ''); ?>
              <option value="<?= h($segmentNo) ?>" <?= ((string) ($record['SegmentNo'] ?? '') === $segmentNo) ? 'selected' : '' ?>>
                <?= h($segmentNo . ' - ' . (string) ($segment['SegmentName'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Segment Code</label>
          <input id="segmentValueSegmentCode" class="form-control" type="text" name="SegmentCode" value="<?= h((string) ($record['SegmentCode'] ?? '')) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Segment Name</label>
          <input id="segmentValueSegmentName" class="form-control" type="text" name="SegmentName" value="<?= h((string) ($record['SegmentName'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">External ID</label>
          <input class="form-control" type="text" name="SegmentExternalID" value="<?= h((string) ($record['SegmentExternalID'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Parent Value ID</label>
          <input class="form-control" type="number" min="1" name="ParentSegmentValueID" value="<?= h((string) ($record['ParentSegmentValueID'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Parent Segment No</label>
          <input class="form-control" type="number" min="1" name="ParentSegmentNo" value="<?= h((string) ($record['ParentSegmentNo'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Parent Segment Code</label>
          <input class="form-control" type="text" name="ParentSegmentCode" value="<?= h((string) ($record['ParentSegmentCode'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Sort Order</label>
          <input class="form-control" type="number" name="SortOrder" value="<?= h((string) ($record['SortOrder'] ?? '0')) ?>">
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="ActiveFlag" id="segmentValueActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="segmentValueActiveFlag">Active</label>
        </div>

        <div class="d-flex justify-content-between">
          <a id="segment-values-back-btn" href="index.php?route=segment-values/list" class="btn btn-secondary">Back</a>
          <button id="segment-values-save-btn" type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
