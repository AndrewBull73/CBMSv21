<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('budget_reduction_status_badge_class')) {
    function budget_reduction_status_badge_class(string $status): string
    {
        return strtoupper(trim($status)) === 'ELIGIBLE' ? 'text-bg-success' : 'text-bg-secondary';
    }
}

$ctx = is_array($context ?? null) ? $context : [];
$fy = (int) ($ctx['FiscalYearID'] ?? 0);
$ver = (int) ($ctx['VersionID'] ?? 0);
$currentVersionLabel = trim((string) ($currentVersion['VersionLabel'] ?? ''));
$form = is_array($form ?? null) ? $form : [];
$preview = is_array($preview ?? null) ? $preview : null;
$previewRows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
$previewSummary = is_array($preview['summary'] ?? null) ? $preview['summary'] : [];
$mappedDimensions = is_array($mappedDimensions ?? null) ? $mappedDimensions : [];
$dataObjectOptions = is_array($dataObjectOptions ?? null) ? $dataObjectOptions : [];
$dimensionOptions = is_array($dimensionOptions ?? null) ? $dimensionOptions : [];
$sessionScopeDataObjectCode = trim((string) ($sessionScopeDataObjectCode ?? ($form['SessionScopeDataObjectCode'] ?? '')));
$csrf = h(csrf_token());
$screenHeader = [
    'title' => 'Budget Reduction Wizard',
    'icon' => 'bi-dash-square',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h((string) $fy) ?></strong>
        <?php if ($currentVersionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($currentVersionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <?php if ($isExecutionVersion): ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Current Authorized</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($openingSummary['CurrentAuthorizedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Released Authority</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($openingSummary['ReleasedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Reserved</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($openingSummary['ReservedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Committed</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($openingSummary['CommittedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this wizard to preview bulk or rule-based budget cuts against existing execution balance lines. The wizard does not approve reductions directly. It generates a draft Supplementary Budget batch with negative lines for later approval.
      </div>

      <?php if (!$supportsSupplementaryFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Supplementary budget tables are not installed.</strong> Run <code>create_budget_execution_supplementaries_v1.sql</code> before using Budget Reductions.
        </div>
      <?php endif; ?>

      <?php if (!$supportsExecutionFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Execution foundation tables are not installed.</strong> Run <code>create_budget_execution_foundation_v1.sql</code> before using Budget Reductions.
        </div>
      <?php endif; ?>

      <?php if (!$isExecutionVersion): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-0">
          The current context is not an execution version. Select an execution version from Budget Execution Setup first.
        </div>
      <?php else: ?>
        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Reduction Scope And Method</h5>
          </div>
          <div class="card-body">
            <form method="post" action="index.php?route=execution/preview-budget-reduction">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Draft Supplementary Title</label>
                  <input class="form-control" type="text" name="ReductionTitle" value="<?= h((string) ($form['ReductionTitle'] ?? '')) ?>" placeholder="e.g. Travel reduction exercise Q1">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Reduction Method</label>
                  <select class="form-select" name="ReductionMethod">
                    <option value="PERCENTAGE" <?= strtoupper((string) ($form['ReductionMethod'] ?? 'PERCENTAGE')) === 'PERCENTAGE' ? 'selected' : '' ?>>Percentage</option>
                    <option value="FIXED_PER_LINE" <?= strtoupper((string) ($form['ReductionMethod'] ?? '')) === 'FIXED_PER_LINE' ? 'selected' : '' ?>>Fixed Amount Per Line</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Reduction Value</label>
                  <input class="form-control" type="number" name="ReductionValue" min="0.01" step="0.01" value="<?= h((string) ($form['ReductionValue'] ?? '')) ?>" required>
                </div>
              </div>

              <div class="mt-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="ReductionNotes" rows="3" placeholder="Optional note to carry into the generated supplementary batch"><?= h((string) ($form['ReductionNotes'] ?? '')) ?></textarea>
              </div>

              <div class="row g-3 mt-1">
                <div class="col-md-4">
                  <label class="form-label">DataObjectCode</label>
                  <select class="form-select" name="DataObjectCode">
                    <option value="">All lines within current session scope</option>
                    <?php foreach ($dataObjectOptions as $option): ?>
                      <?php
                      $code = (string) ($option['DataObjectCode'] ?? '');
                      $name = (string) ($option['DataObjectName'] ?? '');
                      $depth = (int) ($option['Depth'] ?? 0);
                      ?>
                      <option value="<?= h($code) ?>" <?= ((string) ($form['DataObjectCode'] ?? '')) === $code ? 'selected' : '' ?>>
                        <?= h(str_repeat('.. ', max(0, $depth)) . $code . ($name !== '' ? ' - ' . $name : '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($sessionScopeDataObjectCode !== ''): ?>
                    <div class="small text-muted mt-1">List is limited to the current session scope and its child data objects.</div>
                  <?php endif; ?>
                </div>

                <?php foreach ($mappedDimensions as $dimension): ?>
                  <?php
                  $dimensionCode = (string) ($dimension['Code'] ?? '');
                  $dimensionLabel = (string) ($dimension['Label'] ?? $dimensionCode);
                  $segmentName = trim((string) ($dimension['SegmentName'] ?? ''));
                  $segmentNo = (int) ($dimension['SegmentNo'] ?? 0);
                  $selectedValue = (string) (($form['DimensionFilters'][$dimensionCode] ?? ''));
                  $options = is_array($dimensionOptions[$dimensionCode] ?? null) ? $dimensionOptions[$dimensionCode] : [];
                  ?>
                  <div class="col-md-4">
                    <label class="form-label"><?= h($dimensionLabel) ?></label>
                    <select
                      class="form-select js-budget-reduction-dimension"
                      name="DimensionFilters[<?= h($dimensionCode) ?>]"
                      data-dimension-code="<?= h($dimensionCode) ?>"
                      data-segment-no="<?= h((string) $segmentNo) ?>"
                    >
                      <option value="">All <?= h($dimensionLabel) ?> values</option>
                      <?php foreach ($options as $option): ?>
                        <?php $valueCode = (string) ($option['ValueCode'] ?? ''); ?>
                        <?php $valueLabel = (string) ($option['ValueLabel'] ?? $valueCode); ?>
                        <?php $parentSegmentNo = (string) ($option['ParentSegmentNo'] ?? ''); ?>
                        <?php $parentSegmentCode = (string) ($option['ParentSegmentCode'] ?? ''); ?>
                        <option
                          value="<?= h($valueCode) ?>"
                          data-parent-segment-no="<?= h($parentSegmentNo) ?>"
                          data-parent-segment-code="<?= h($parentSegmentCode) ?>"
                          <?= $selectedValue === $valueCode ? 'selected' : '' ?>
                        >
                          <?= h($valueCode . ($valueLabel !== $valueCode ? ' - ' . $valueLabel : '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <?php if ($segmentName !== ''): ?>
                      <div class="small text-muted mt-1">Mapped from segment: <?= h($segmentName) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="small text-muted mt-3 mb-3">Mapped dimensions only are shown here. At least one reduction filter is required. Reductions are blocked if they would take a line below already released authority.</div>

              <button type="submit" class="btn btn-primary btn-sm" <?= (!$supportsExecutionFoundation || !$supportsSupplementaryFoundation) ? 'disabled' : '' ?>>
                <i class="bi bi-search me-1"></i>Preview Reduction
              </button>
            </form>
          </div>
        </div>

        <?php if ($preview !== null): ?>
          <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
              <div class="card shadow-sm h-100">
                <div class="card-body">
                  <div class="text-muted small">Matched Lines</div>
                  <div class="fs-4 fw-semibold"><?= h((string) ($previewSummary['MatchedLineCount'] ?? 0)) ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="card shadow-sm h-100">
                <div class="card-body">
                  <div class="text-muted small">Eligible Lines</div>
                  <div class="fs-4 fw-semibold"><?= h((string) ($previewSummary['EligibleLineCount'] ?? 0)) ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="card shadow-sm h-100">
                <div class="card-body">
                  <div class="text-muted small">Blocked Lines</div>
                  <div class="fs-4 fw-semibold"><?= h((string) ($previewSummary['BlockedLineCount'] ?? 0)) ?></div>
                </div>
              </div>
            </div>
            <div class="col-6 col-xl-3">
              <div class="card shadow-sm h-100">
                <div class="card-body">
                  <div class="text-muted small">Eligible Reduction Total</div>
                  <div class="fs-4 fw-semibold"><?= h(number_format((float) ($previewSummary['EligibleReductionTotal'] ?? 0), 2)) ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="card shadow-sm mb-4">
            <div class="card-header">
              <h5 class="mb-0">Reduction Preview</h5>
            </div>
            <div class="card-body">
              <?php if (empty($previewRows)): ?>
                <div class="text-muted">No execution balance lines matched the selected scope.</div>
              <?php else: ?>
                <form method="post" action="index.php?route=execution/generate-budget-reduction">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="ReductionTitle" value="<?= h((string) ($form['ReductionTitle'] ?? '')) ?>">
                  <input type="hidden" name="ReductionNotes" value="<?= h((string) ($form['ReductionNotes'] ?? '')) ?>">
                  <input type="hidden" name="ReductionMethod" value="<?= h((string) ($form['ReductionMethod'] ?? '')) ?>">
                  <input type="hidden" name="ReductionValue" value="<?= h((string) ($form['ReductionValue'] ?? '')) ?>">
                  <input type="hidden" name="DataObjectCode" value="<?= h((string) ($form['DataObjectCode'] ?? '')) ?>">
                  <?php foreach ($mappedDimensions as $dimension): ?>
                    <?php $dimensionCode = (string) ($dimension['Code'] ?? ''); ?>
                    <input type="hidden" name="DimensionFilters[<?= h($dimensionCode) ?>]" value="<?= h((string) ($form['DimensionFilters'][$dimensionCode] ?? '')) ?>">
                  <?php endforeach; ?>

                  <div class="small text-muted mb-3">Only eligible lines will be generated into the draft supplementary batch. You can deselect any eligible line before generation.</div>

                  <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-3">
                      <thead class="table-light">
                        <tr>
                          <th>Select</th>
                          <th>Status</th>
                          <th>DataScope</th>
                          <th>Program</th>
                          <th>Project</th>
                          <th>Economic</th>
                          <th>Bid Title</th>
                          <th class="text-end">Current</th>
                          <th class="text-end">Released</th>
                          <th class="text-end">Headroom</th>
                          <th class="text-end">Reduction</th>
                          <th class="text-end">Resulting</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($previewRows as $row): ?>
                          <?php
                          $rowId = (int) ($row['ExecutionOpeningBalanceID'] ?? 0);
                          $rowStatus = (string) ($row['ReductionStatus'] ?? '');
                          $isEligible = strtoupper(trim($rowStatus)) === 'ELIGIBLE';
                          ?>
                          <tr>
                            <td>
                              <?php if ($isEligible): ?>
                                <input class="form-check-input" type="checkbox" name="SelectedOpeningBalanceIDs[]" value="<?= $rowId ?>" checked>
                              <?php else: ?>
                                <span class="text-muted small">Blocked</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge <?= h(budget_reduction_status_badge_class($rowStatus)) ?>"><?= h($rowStatus) ?></span>
                              <?php if (!$isEligible && trim((string) ($row['ReductionStatusReason'] ?? '')) !== ''): ?>
                                <div class="small text-muted mt-1"><?= h((string) ($row['ReductionStatusReason'] ?? '')) ?></div>
                              <?php endif; ?>
                            </td>
                            <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                            <td><?= h((string) ($row['ProgramID'] ?? '')) ?></td>
                            <td><?= h((string) ($row['ProjectID'] ?? '')) ?></td>
                            <td><?= h((string) ($row['EconomicItemID'] ?? '')) ?></td>
                            <td><?= h((string) ($row['BidTitle'] ?? '')) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['CurrentAuthorizedAmount'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['PlannedReleaseAmountTotal'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['MaxReducibleAmount'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['ProposedReductionAmount'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['ResultingAuthorizedAmount'] ?? 0), 2)) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>

                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Generate Draft Supplementary Batch
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const selects = Array.from(document.querySelectorAll('.js-budget-reduction-dimension'));
  if (selects.length === 0) {
    return;
  }

  const bySegmentNo = new Map();
  selects.forEach(function (select) {
    const segmentNo = String(select.dataset.segmentNo || '');
    if (segmentNo !== '') {
      bySegmentNo.set(segmentNo, select);
    }
    select._allOptions = Array.from(select.querySelectorAll('option')).map(function (option) {
      return {
        value: option.value,
        label: option.textContent || '',
        parentSegmentNo: option.dataset.parentSegmentNo || '',
        parentSegmentCode: option.dataset.parentSegmentCode || '',
        selected: option.selected
      };
    });
  });

  function rebuildSelect(select) {
    const originalValue = select.value;
    const options = Array.isArray(select._allOptions) ? select._allOptions : [];
    const filtered = options.filter(function (option, index) {
      if (index === 0 || option.value === '') {
        return true;
      }
      if (option.parentSegmentNo === '' || option.parentSegmentCode === '') {
        return true;
      }
      const parentSelect = bySegmentNo.get(option.parentSegmentNo);
      if (!parentSelect) {
        return true;
      }
      const parentValue = parentSelect.value || '';
      if (parentValue === '') {
        return true;
      }
      return option.parentSegmentCode === parentValue;
    });

    select.innerHTML = '';
    filtered.forEach(function (option) {
      const node = document.createElement('option');
      node.value = option.value;
      node.textContent = option.label;
      if (option.parentSegmentNo !== '') {
        node.dataset.parentSegmentNo = option.parentSegmentNo;
      }
      if (option.parentSegmentCode !== '') {
        node.dataset.parentSegmentCode = option.parentSegmentCode;
      }
      select.appendChild(node);
    });

    const stillValid = filtered.some(function (option) {
      return option.value === originalValue;
    });
    select.value = stillValid ? originalValue : '';
  }

  function refreshAll() {
    selects.forEach(rebuildSelect);
  }

  selects.forEach(function (select) {
    select.addEventListener('change', refreshAll);
  });

  refreshAll();
});
</script>
