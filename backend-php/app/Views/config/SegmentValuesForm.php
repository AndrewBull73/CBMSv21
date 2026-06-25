<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selected')) {
    function selected(string $actual, string $expected): string
    {
        return strcasecmp(trim($actual), $expected) === 0 ? 'selected' : '';
    }
}

$record = is_array($record ?? null) ? $record : [];
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$segments = is_array($segments ?? null) ? $segments : [];
$dataObjectCodes = is_array($dataObjectCodes ?? null) ? $dataObjectCodes : [];
$id = (int) ($record['SegmentValueID'] ?? 0);
$isEditing = $id > 0;
$currentFiscalYearId = (string) ($record['FiscalYearID'] ?? ($ctxFy > 0 ? (string) $ctxFy : ''));
$screenHeader = [
    'title' => $isEditing ? 'Edit Segment Value' : 'Create Segment Value',
    'icon' => 'bi-diagram-3',
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

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Maintain fiscal-year segment values used for account structures, organisational scope, and hierarchy mapping.
      </div>

      <form method="post" action="index.php?route=segment-values/save" id="segment-values-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="SegmentValueID" value="<?= $id ?>">

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Context And Key</h5>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Fiscal Year</label>
              <select id="segmentValueFiscalYearID" name="FiscalYearID" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($fiscalYears as $fy): ?>
                  <?php $fyId = (string) ($fy['FiscalYearID'] ?? ''); ?>
                  <option value="<?= h($fyId) ?>" <?= selected($currentFiscalYearId, $fyId) ?>>
                    <?= h($fyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Select a Fiscal Year.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Org Unit Code</label>
              <input id="segmentValueDataObjectCode" class="form-control" list="segmentValueDataObjects" name="DataObjectCode" value="<?= h((string) ($record['DataObjectCode'] ?? '')) ?>" required>
              <datalist id="segmentValueDataObjects">
                <?php foreach ($dataObjectCodes as $doc): ?>
                  <option value="<?= h((string) ($doc['DataObjectCode'] ?? '')) ?>"><?= h((string) ($doc['DataObjectName'] ?? '')) ?></option>
                <?php endforeach; ?>
              </datalist>
              <div class="invalid-feedback">Enter or select an Org Unit Code.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Segment</label>
              <select id="segmentValueSegmentNo" name="SegmentNo" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($segments as $segment): ?>
                  <?php $segmentNo = (string) ($segment['SegmentNo'] ?? ''); ?>
                  <option value="<?= h($segmentNo) ?>" <?= selected((string) ($record['SegmentNo'] ?? ''), $segmentNo) ?>>
                    <?= h($segmentNo . ' - ' . (string) ($segment['SegmentName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Select a Segment.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Segment Code</label>
              <input id="segmentValueSegmentCode" class="form-control" type="text" name="SegmentCode" value="<?= h((string) ($record['SegmentCode'] ?? '')) ?>" required>
              <div class="invalid-feedback">Enter a Segment Code.</div>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Value Details</h5>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Segment Name</label>
              <input id="segmentValueSegmentName" class="form-control" type="text" name="SegmentName" value="<?= h((string) ($record['SegmentName'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">External ID</label>
              <input class="form-control" type="text" name="SegmentExternalID" value="<?= h((string) ($record['SegmentExternalID'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sort Order</label>
              <input id="segmentValueSortOrder" class="form-control" type="number" name="SortOrder" value="<?= h((string) ($record['SortOrder'] ?? '0')) ?>">
              <div class="invalid-feedback">Sort Order must be numeric.</div>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Parent Link</h5>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Parent Value ID</label>
              <input id="segmentValueParentValueID" class="form-control" type="number" min="1" name="ParentSegmentValueID" value="<?= h((string) ($record['ParentSegmentValueID'] ?? '')) ?>">
              <div class="invalid-feedback">Parent Value ID must be greater than zero and cannot be this same value.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Parent Segment</label>
              <select id="segmentValueParentSegmentNo" class="form-select" name="ParentSegmentNo">
                <option value=""></option>
                <?php foreach ($segments as $segment): ?>
                  <?php $segmentNo = (string) ($segment['SegmentNo'] ?? ''); ?>
                  <option value="<?= h($segmentNo) ?>" <?= selected((string) ($record['ParentSegmentNo'] ?? ''), $segmentNo) ?>>
                    <?= h($segmentNo . ' - ' . (string) ($segment['SegmentName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Select Parent Segment when Parent Segment Code is entered.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Parent Org Unit</label>
              <input id="segmentValueParentDataObjectCode" class="form-control" type="text" name="ParentSegmentDataObjectCode" value="<?= h((string) ($record['ParentSegmentDataObjectCode'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Parent Segment Code</label>
              <input id="segmentValueParentSegmentCode" class="form-control" type="text" name="ParentSegmentCode" value="<?= h((string) ($record['ParentSegmentCode'] ?? '')) ?>">
              <div class="invalid-feedback">Enter Parent Segment Code when Parent Segment is selected.</div>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Status</h5>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="ActiveFlag" id="segmentValueActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="segmentValueActiveFlag">Active</label>
          </div>
        </section>

        <div class="d-flex justify-content-between">
          <a id="segment-values-back-btn" href="index.php?route=segment-values/list" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
          </a>
          <button id="segment-values-save-btn" type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-save me-1"></i>Save Segment Value
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('segment-values-form');
  if (!form) {
    return;
  }

  const byId = (id) => document.getElementById(id);
  const fields = {
    segmentValueId: form.querySelector('input[name="SegmentValueID"]'),
    fiscalYear: byId('segmentValueFiscalYearID'),
    dataObjectCode: byId('segmentValueDataObjectCode'),
    segmentNo: byId('segmentValueSegmentNo'),
    segmentCode: byId('segmentValueSegmentCode'),
    sortOrder: byId('segmentValueSortOrder'),
    parentValueId: byId('segmentValueParentValueID'),
    parentSegmentNo: byId('segmentValueParentSegmentNo'),
    parentDataObjectCode: byId('segmentValueParentDataObjectCode'),
    parentSegmentCode: byId('segmentValueParentSegmentCode'),
  };

  const setInvalid = (field, invalid) => {
    if (!field) {
      return;
    }
    field.classList.toggle('is-invalid', invalid);
  };

  const intValue = (field) => {
    const value = (field?.value || '').trim();
    return value === '' ? null : Number.parseInt(value, 10);
  };

  const validate = () => {
    let valid = true;

    [fields.fiscalYear, fields.dataObjectCode, fields.segmentNo, fields.segmentCode].forEach((field) => {
      const invalid = (field?.value || '').trim() === '';
      setInvalid(field, invalid);
      valid = valid && !invalid;
    });

    const parentValueId = intValue(fields.parentValueId);
    const segmentValueId = intValue(fields.segmentValueId);
    const invalidParentValueId = parentValueId !== null && (parentValueId <= 0 || (segmentValueId !== null && parentValueId === segmentValueId));
    setInvalid(fields.parentValueId, invalidParentValueId);
    valid = valid && !invalidParentValueId;

    const parentSegmentNo = (fields.parentSegmentNo?.value || '').trim();
    const parentSegmentCode = (fields.parentSegmentCode?.value || '').trim();
    const invalidParentNo = parentSegmentCode !== '' && parentSegmentNo === '';
    const invalidParentCode = parentSegmentNo !== '' && parentSegmentCode === '';
    setInvalid(fields.parentSegmentNo, invalidParentNo);
    setInvalid(fields.parentSegmentCode, invalidParentCode);
    valid = valid && !invalidParentNo && !invalidParentCode;

    return valid;
  };

  form.addEventListener('submit', (event) => {
    if (!validate()) {
      event.preventDefault();
      event.stopPropagation();
      form.querySelector('.is-invalid')?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
      });
    }
  });

  form.querySelectorAll('input, select').forEach((field) => {
    field.addEventListener('input', validate);
    field.addEventListener('change', validate);
  });
});
</script>
