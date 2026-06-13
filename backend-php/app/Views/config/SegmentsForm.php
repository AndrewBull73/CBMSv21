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
$dimensions = is_array($dimensions ?? null) ? $dimensions : [];
$groups = is_array($groups ?? null) ? $groups : [];
$parentSegments = is_array($parentSegments ?? null) ? $parentSegments : [];
$startPointValue = (int) ($record['StartPoint'] ?? 0) > 0 ? (string) $record['StartPoint'] : '';
$endPointValue = (int) ($record['EndPoint'] ?? 0) > 0 ? (string) $record['EndPoint'] : '';
$id = (int) ($record['SegmentID'] ?? 0);
$isEditing = $id > 0;
$screenHeader = [
    'title' => $isEditing ? 'Edit Segment' : 'Create Segment',
    'icon' => 'bi-sliders2-vertical',
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
        Maintain the segment definition used to interpret account strings, imported segment values, and module-specific dimension mappings.
      </div>

      <form method="post" action="index.php?route=segments/save" id="segments-form" novalidate>
        <?= csrf_field() ?>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Identity</h5>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Segment ID</label>
              <input id="segmentId" class="form-control" type="number" min="1" name="SegmentID" value="<?= h((string) ($record['SegmentID'] ?? '')) ?>" <?= $isEditing ? 'readonly' : 'required' ?>>
              <div class="invalid-feedback">Enter a Segment ID greater than zero.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Segment Code</label>
              <input id="segmentCode" class="form-control" type="text" name="SegmentCode" value="<?= h((string) ($record['SegmentCode'] ?? '')) ?>" required>
              <div class="invalid-feedback">Enter a Segment Code.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Segment Name</label>
              <input id="segmentName" class="form-control" type="text" name="SegmentName" value="<?= h((string) ($record['SegmentName'] ?? '')) ?>" required>
              <div class="invalid-feedback">Enter a Segment Name.</div>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Structure</h5>
          <div class="row g-3">
            <div class="col-md-2">
              <label class="form-label">Display Order</label>
              <input class="form-control" type="number" name="DisplayOrder" value="<?= h((string) ($record['DisplayOrder'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Delimiter</label>
              <input class="form-control" type="text" maxlength="1" name="Delimiter" value="<?= h((string) ($record['Delimiter'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Min Length</label>
              <input id="segmentMinLength" class="form-control" type="number" min="0" name="MinLength" value="<?= h((string) ($record['MinLength'] ?? '')) ?>">
              <div class="invalid-feedback">Min Length cannot be greater than Max Length.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Max Length</label>
              <input id="segmentMaxLength" class="form-control" type="number" min="0" name="MaxLength" value="<?= h((string) ($record['MaxLength'] ?? '')) ?>">
              <div class="invalid-feedback">Max Length must be greater than or equal to Min Length.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Start Point</label>
              <input id="segmentStartPoint" class="form-control" type="number" min="1" name="StartPoint" value="<?= h($startPointValue) ?>">
              <div class="invalid-feedback">Start Point must be greater than zero when entered.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">End Point</label>
              <input id="segmentEndPoint" class="form-control" type="number" min="1" name="EndPoint" value="<?= h($endPointValue) ?>">
              <div class="invalid-feedback">End Point must be greater than or equal to Start Point when both are entered.</div>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Classification</h5>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">CBMS Dimension</label>
              <input id="segmentDimension" class="form-control" list="segmentDimensions" name="CBMSDimension" value="<?= h((string) ($record['CBMSDimension'] ?? '')) ?>" required>
              <datalist id="segmentDimensions">
                <?php foreach ($dimensions as $dimension): ?>
                  <option value="<?= h($dimension) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <div class="invalid-feedback">Enter or select a CBMS Dimension.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Segment Group</label>
              <input id="segmentGroup" class="form-control" list="segmentGroups" name="SegmentGroup" value="<?= h((string) ($record['SegmentGroup'] ?? '')) ?>" required>
              <datalist id="segmentGroups">
                <?php foreach ($groups as $group): ?>
                  <option value="<?= h($group) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <div class="invalid-feedback">Enter or select a Segment Group.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Type</label>
              <input class="form-control" type="text" maxlength="1" name="Type" value="<?= h((string) ($record['Type'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Business Area</label>
              <input class="form-control" type="text" maxlength="1" name="DefaultBusinessArea" value="<?= h((string) ($record['DefaultBusinessArea'] ?? '')) ?>">
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Usage And Parent Rules</h5>
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Parent Segment</label>
              <select id="segmentParentDefault" class="form-select" name="ParentSegmentNoDefault">
                <option value=""></option>
                <?php foreach ($parentSegments as $parentSegment): ?>
                  <?php
                    $parentSegmentId = (int) ($parentSegment['SegmentID'] ?? 0);
                    if ($parentSegmentId <= 0 || $parentSegmentId === $id) {
                        continue;
                    }
                    $parentLabel = trim((string) ($parentSegment['SegmentCode'] ?? ''));
                    $parentName = trim((string) ($parentSegment['SegmentName'] ?? ''));
                    if ($parentName !== '') {
                        $parentLabel .= $parentLabel !== '' ? ' - ' . $parentName : $parentName;
                    }
                  ?>
                  <option value="<?= $parentSegmentId ?>" <?= selected((string) ($record['ParentSegmentNoDefault'] ?? ''), (string) $parentSegmentId) ?>>
                    <?= h($parentLabel !== '' ? $parentLabel : (string) $parentSegmentId) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Parent Segment cannot be the same as this Segment ID.</div>
            </div>
            <div class="col-md-9">
              <div class="row g-2">
                <div class="col-md-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="segmentUsedInFinancialAccount" name="UsedInFinancialAccount" <?= ((int) ($record['UsedInFinancialAccount'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="segmentUsedInFinancialAccount">Financial</label>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="segmentUsedInStrategicPlanning" name="UsedInStrategicPlanning" <?= ((int) ($record['UsedInStrategicPlanning'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="segmentUsedInStrategicPlanning">Strategic</label>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="segmentUsedInOrgStructure" name="UsedInOrgStructure" <?= ((int) ($record['UsedInOrgStructure'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="segmentUsedInOrgStructure">Org Structure</label>
                  </div>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Editable</label>
                  <select class="form-select" name="Editable">
                    <option value=""></option>
                    <option value="Y" <?= selected((string) ($record['Editable'] ?? ''), 'Y') ?>>Y</option>
                    <option value="N" <?= selected((string) ($record['Editable'] ?? ''), 'N') ?>>N</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Static</label>
                  <select class="form-select" name="Static">
                    <option value=""></option>
                    <option value="Y" <?= selected((string) ($record['Static'] ?? ''), 'Y') ?>>Y</option>
                    <option value="N" <?= selected((string) ($record['Static'] ?? ''), 'N') ?>>N</option>
                  </select>
                </div>
                <div class="col-md-2">
                  <div class="form-check form-switch mt-md-4">
                    <input class="form-check-input" type="checkbox" id="segmentParentRequired" name="ParentRequired" <?= ((int) ($record['ParentRequired'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="segmentParentRequired">Parent Required</label>
                  </div>
                </div>
                <div class="col-12">
                  <div id="segmentUsageWarning" class="alert alert-warning py-2 px-3 mb-0 d-none">
                    Select at least one usage flag: Financial, Strategic, or Org Structure.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h5 class="border-bottom pb-2 mb-3">Attribute Labels</h5>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Attribute 1</label>
              <input class="form-control" type="text" name="Attribute1Name" value="<?= h((string) ($record['Attribute1Name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Attribute 2</label>
              <input class="form-control" type="text" name="Attribute2Name" value="<?= h((string) ($record['Attribute2Name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Attribute 3</label>
              <input class="form-control" type="text" name="Attribute3Name" value="<?= h((string) ($record['Attribute3Name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Attribute 4</label>
              <input class="form-control" type="text" name="Attribute4Name" value="<?= h((string) ($record['Attribute4Name'] ?? '')) ?>">
            </div>
          </div>
        </section>

        <div class="d-flex justify-content-between">
          <a id="segments-back-btn" href="index.php?route=segments/list" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
          </a>
          <button id="segments-save-btn" type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-save me-1"></i>Save Segment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('segments-form');
  if (!form) {
    return;
  }

  const byId = (id) => document.getElementById(id);
  const fields = {
    segmentId: byId('segmentId'),
    segmentCode: byId('segmentCode'),
    segmentName: byId('segmentName'),
    minLength: byId('segmentMinLength'),
    maxLength: byId('segmentMaxLength'),
    startPoint: byId('segmentStartPoint'),
    endPoint: byId('segmentEndPoint'),
    dimension: byId('segmentDimension'),
    group: byId('segmentGroup'),
    parentDefault: byId('segmentParentDefault'),
  };
  const usageChecks = [
    byId('segmentUsedInFinancialAccount'),
    byId('segmentUsedInStrategicPlanning'),
    byId('segmentUsedInOrgStructure'),
  ].filter(Boolean);
  const usageWarning = byId('segmentUsageWarning');

  const intValue = (field) => {
    const value = (field?.value || '').trim();
    return value === '' ? null : Number.parseInt(value, 10);
  };

  const setInvalid = (field, invalid) => {
    if (!field) {
      return;
    }
    field.classList.toggle('is-invalid', invalid);
  };

  const validate = () => {
    let valid = true;

    const requiredTextFields = [
      fields.segmentCode,
      fields.segmentName,
      fields.dimension,
      fields.group,
    ];
    requiredTextFields.forEach((field) => {
      const invalid = (field?.value || '').trim() === '';
      setInvalid(field, invalid);
      valid = valid && !invalid;
    });

    const segmentId = intValue(fields.segmentId);
    const invalidSegmentId = !segmentId || segmentId <= 0;
    setInvalid(fields.segmentId, invalidSegmentId);
    valid = valid && !invalidSegmentId;

    const startPoint = intValue(fields.startPoint);
    const endPoint = intValue(fields.endPoint);
    const invalidStart = startPoint !== null && startPoint <= 0;
    const invalidEnd = (endPoint !== null && endPoint <= 0) || (startPoint !== null && endPoint !== null && endPoint < startPoint);
    setInvalid(fields.startPoint, invalidStart);
    setInvalid(fields.endPoint, invalidEnd);
    valid = valid && !invalidStart && !invalidEnd;

    const minLength = intValue(fields.minLength);
    const maxLength = intValue(fields.maxLength);
    const invalidLengthRange = minLength !== null && maxLength !== null && minLength > maxLength;
    setInvalid(fields.minLength, invalidLengthRange);
    setInvalid(fields.maxLength, invalidLengthRange);
    valid = valid && !invalidLengthRange;

    const parentDefault = intValue(fields.parentDefault);
    const invalidParent = parentDefault !== null && segmentId !== null && parentDefault === segmentId;
    setInvalid(fields.parentDefault, invalidParent);
    valid = valid && !invalidParent;

    const hasUsage = usageChecks.some((field) => field.checked);
    usageChecks.forEach((field) => field.classList.toggle('is-invalid', !hasUsage));
    if (usageWarning) {
      usageWarning.classList.toggle('d-none', hasUsage);
    }
    valid = valid && hasUsage;

    return valid;
  };

  form.addEventListener('submit', (event) => {
    if (!validate()) {
      event.preventDefault();
      event.stopPropagation();
      form.querySelector('.is-invalid, #segmentUsageWarning:not(.d-none)')?.scrollIntoView({
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
