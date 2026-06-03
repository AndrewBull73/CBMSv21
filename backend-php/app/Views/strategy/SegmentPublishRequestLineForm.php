<?php
declare(strict_types=1);
/** @var array $request */
/** @var array|null $line */
/** @var array $dimensions */
/** @var array $dataObjectOptions */
/** @var array $availableSegments */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
$line = is_array($line ?? null) ? $line : null;
$selectedDimension = (string) ($line['StrategicDimensionCode'] ?? '');
$selectedDataObjectCode = (string) ($line['DataObjectCode'] ?? '');
$selectedParentSegmentNo = (int) ($line['ParentSegmentNo'] ?? 0);
$selectedDimensionMeta = null;
foreach (($dimensions ?? []) as $dimension) {
    if ((string) ($dimension['Code'] ?? '') === $selectedDimension) {
        $selectedDimensionMeta = $dimension;
        break;
    }
}
$selectedMinLength = (int) ($selectedDimensionMeta['MinLength'] ?? 0);
$selectedMaxLength = (int) ($selectedDimensionMeta['MaxLength'] ?? 0);
$selectedParentSegmentMeta = null;
foreach (($availableSegments ?? []) as $segment) {
    if ((int) ($segment['SegmentNo'] ?? 0) === $selectedParentSegmentNo) {
        $selectedParentSegmentMeta = $segment;
        break;
    }
}
$selectedParentMinLength = (int) ($selectedParentSegmentMeta['MinLength'] ?? 0);
$selectedParentMaxLength = (int) ($selectedParentSegmentMeta['MaxLength'] ?? 0);
?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-1"><?= $line ? 'Edit Publication Line' : 'Add Publication Line' ?></h1>
      <div class="text-muted"><?= h((string) ($request['RequestTitle'] ?? '')) ?></div>
    </div>
    <a href="index.php?route=strategy-publish/request-view&id=<?= (int) ($request['StrategicSegmentPublishRequestID'] ?? 0) ?>" class="btn btn-outline-secondary">Back</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="index.php?route=strategy-publish/save-line">
        <input type="hidden" name="StrategicSegmentPublishRequestID" value="<?= (int) ($request['StrategicSegmentPublishRequestID'] ?? 0) ?>">
        <input type="hidden" name="StrategicSegmentPublishRequestLineID" value="<?= (int) ($line['StrategicSegmentPublishRequestLineID'] ?? 0) ?>">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Strategic Dimension</label>
            <select name="StrategicDimensionCode" class="form-select" required>
              <option value="">Select dimension</option>
              <?php foreach ($dimensions as $dimension): ?>
                <option
                  value="<?= h((string) ($dimension['Code'] ?? '')) ?>"
                  data-segment-no="<?= (int) ($dimension['SegmentNo'] ?? 0) ?>"
                  data-min-length="<?= (int) ($dimension['MinLength'] ?? 0) ?>"
                  data-max-length="<?= (int) ($dimension['MaxLength'] ?? 0) ?>"
                  <?= $selectedDimension === (string) ($dimension['Code'] ?? '') ? 'selected' : '' ?>
                >
                  <?= h((string) ($dimension['Label'] ?? '')) ?><?= !empty($dimension['SegmentNo']) ? ' (Segment ' . (int) $dimension['SegmentNo'] . ')' : ' (Not mapped)' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">DataObjectCode / Ministry</label>
            <select name="DataObjectCode" class="form-select" required>
              <option value="">Select DataObjectCode</option>
              <option value="0" <?= $selectedDataObjectCode === '0' ? 'selected' : '' ?>>0 / Global</option>
              <?php foreach (($dataObjectOptions ?? []) as $option): ?>
                <?php $optionCode = (string) ($option['DataObjectCode'] ?? ''); ?>
                <?php $optionName = (string) ($option['DataObjectName'] ?? $option['OrgUnitName'] ?? ''); ?>
                <option value="<?= h($optionCode) ?>" <?= $selectedDataObjectCode === $optionCode ? 'selected' : '' ?>>
                  <?= h($optionCode . ' / ' . $optionName) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Segment Code</label>
            <input
              type="text"
              name="SegmentCode"
              id="SegmentCode"
              class="form-control"
              required
              value="<?= h((string) ($line['SegmentCode'] ?? '')) ?>"
              <?= $selectedMinLength > 0 ? ' minlength="' . $selectedMinLength . '"' : '' ?>
              <?= $selectedMaxLength > 0 ? ' maxlength="' . $selectedMaxLength . '"' : '' ?>
            >
            <div class="form-text" id="SegmentCodeLengthHelp">
              <?php if ($selectedMinLength > 0 || $selectedMaxLength > 0): ?>
                Code length rule:
                <?= $selectedMinLength > 0 ? 'min ' . $selectedMinLength : 'no minimum' ?>
                /
                <?= $selectedMaxLength > 0 ? 'max ' . $selectedMaxLength : 'no maximum' ?>.
              <?php else: ?>
                Length rules will follow the mapped segment definition.
              <?php endif; ?>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Segment Name</label>
            <input type="text" name="SegmentName" class="form-control" required value="<?= h((string) ($line['SegmentName'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Segment External ID</label>
            <input type="text" name="SegmentExternalID" class="form-control" value="<?= h((string) ($line['SegmentExternalID'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Parent Segment No</label>
            <select name="ParentSegmentNo" id="ParentSegmentNo" class="form-select">
              <option value="">No parent</option>
              <?php foreach (($availableSegments ?? []) as $segment): ?>
                <?php $segmentNo = (int) ($segment['SegmentNo'] ?? 0); ?>
                <option
                  value="<?= $segmentNo ?>"
                  data-min-length="<?= (int) ($segment['MinLength'] ?? 0) ?>"
                  data-max-length="<?= (int) ($segment['MaxLength'] ?? 0) ?>"
                  <?= $selectedParentSegmentNo === $segmentNo ? 'selected' : '' ?>
                >
                  <?= h((string) ($segment['SegmentLabel'] ?? ($segmentNo . ' - ' . ($segment['SegmentName'] ?? '')))) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Parent Segment Code</label>
            <input
              type="text"
              name="ParentSegmentCode"
              id="ParentSegmentCode"
              class="form-control"
              value="<?= h((string) ($line['ParentSegmentCode'] ?? '')) ?>"
              <?= $selectedParentMinLength > 0 ? ' minlength="' . $selectedParentMinLength . '"' : '' ?>
              <?= $selectedParentMaxLength > 0 ? ' maxlength="' . $selectedParentMaxLength . '"' : '' ?>
            >
            <div class="form-text" id="ParentSegmentCodeLengthHelp">
              <?php if ($selectedParentMinLength > 0 || $selectedParentMaxLength > 0): ?>
                Parent code length rule:
                <?= $selectedParentMinLength > 0 ? 'min ' . $selectedParentMinLength : 'no minimum' ?>
                /
                <?= $selectedParentMaxLength > 0 ? 'max ' . $selectedParentMaxLength : 'no maximum' ?>.
              <?php else: ?>
                If a parent segment is selected, parent code length rules will follow that segment definition.
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sort Order</label>
            <input type="number" name="SortOrder" class="form-control" value="<?= h((string) ($line['SortOrder'] ?? '')) ?>">
            <div class="form-text">Leave blank to derive from a numeric segment code.</div>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" <?= !isset($line['ActiveFlag']) || !empty($line['ActiveFlag']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
          This first version captures the segment value proposal explicitly. Approval is required before anything is written back to <code>tblSegmentValues</code>.
        </div>

        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn btn-primary">Save Line</button>
          <a href="index.php?route=strategy-publish/request-view&id=<?= (int) ($request['StrategicSegmentPublishRequestID'] ?? 0) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dimensionSelect = document.querySelector('select[name="StrategicDimensionCode"]');
    const segmentCodeInput = document.getElementById('SegmentCode');
    const lengthHelp = document.getElementById('SegmentCodeLengthHelp');
    const parentSegmentSelect = document.getElementById('ParentSegmentNo');
    const parentSegmentCodeInput = document.getElementById('ParentSegmentCode');
    const parentLengthHelp = document.getElementById('ParentSegmentCodeLengthHelp');

    if (!dimensionSelect || !segmentCodeInput || !lengthHelp || !parentSegmentSelect || !parentSegmentCodeInput || !parentLengthHelp) {
        return;
    }

    const applyRules = function () {
        const selected = dimensionSelect.options[dimensionSelect.selectedIndex];
        const minLength = parseInt(selected?.dataset?.minLength || '0', 10) || 0;
        const maxLength = parseInt(selected?.dataset?.maxLength || '0', 10) || 0;

        if (minLength > 0) {
            segmentCodeInput.setAttribute('minlength', String(minLength));
        } else {
            segmentCodeInput.removeAttribute('minlength');
        }

        if (maxLength > 0) {
            segmentCodeInput.setAttribute('maxlength', String(maxLength));
        } else {
            segmentCodeInput.removeAttribute('maxlength');
        }

        if (minLength > 0 || maxLength > 0) {
            lengthHelp.textContent = 'Code length rule: '
                + (minLength > 0 ? 'min ' + minLength : 'no minimum')
                + ' / '
                + (maxLength > 0 ? 'max ' + maxLength : 'no maximum')
                + '.';
        } else {
            lengthHelp.textContent = 'Length rules will follow the mapped segment definition.';
        }
    };

    const applyParentRules = function () {
        const selected = parentSegmentSelect.options[parentSegmentSelect.selectedIndex];
        const minLength = parseInt(selected?.dataset?.minLength || '0', 10) || 0;
        const maxLength = parseInt(selected?.dataset?.maxLength || '0', 10) || 0;
        const hasParent = (selected?.value || '') !== '';

        if (minLength > 0) {
            parentSegmentCodeInput.setAttribute('minlength', String(minLength));
        } else {
            parentSegmentCodeInput.removeAttribute('minlength');
        }

        if (maxLength > 0) {
            parentSegmentCodeInput.setAttribute('maxlength', String(maxLength));
        } else {
            parentSegmentCodeInput.removeAttribute('maxlength');
        }

        if (hasParent && (minLength > 0 || maxLength > 0)) {
            parentLengthHelp.textContent = 'Parent code length rule: '
                + (minLength > 0 ? 'min ' + minLength : 'no minimum')
                + ' / '
                + (maxLength > 0 ? 'max ' + maxLength : 'no maximum')
                + '.';
        } else if (hasParent) {
            parentLengthHelp.textContent = 'A parent segment is selected. Parent code rules will follow that segment definition.';
        } else {
            parentLengthHelp.textContent = 'If a parent segment is selected, parent code length rules will follow that segment definition.';
        }
    };

    dimensionSelect.addEventListener('change', applyRules);
    parentSegmentSelect.addEventListener('change', applyParentRules);
    applyRules();
    applyParentRules();
});
</script>
