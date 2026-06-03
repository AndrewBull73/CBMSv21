<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('renderImportSummaryPills')) {
    function renderImportSummaryPills(array $parts): string
    {
        $html = '';
        foreach ($parts as $part) {
            $label = trim((string) ($part['label'] ?? ''));
            $value = trim((string) ($part['value'] ?? ''));
            if ($label === '') {
                continue;
            }
            $text = $label . ($value !== '' ? ': ' . $value : '');
            $html .= '<span class="badge rounded-pill border text-secondary-emphasis bg-light-subtle me-1 mb-1">' . h($text) . '</span>';
        }
        return $html;
    }
}

$csrf = h(csrf_token());
$fiscalYearId = (int) ($fiscalYearId ?? 0);
$definitions = is_array($definitions ?? null) ? $definitions : [];
$yearLabel = (string) ($context['YearLabel'] ?? '');
$versionLabel = (string) ($context['VersionLabel'] ?? '');
$statusByCode = [];
foreach (($statuses ?? []) as $code => $status) {
    if (is_array($status)) {
        $statusByCode[(string) $code] = $status;
    }
}

$notMappedDimensions = [];
$notImportedDimensions = [];
$partiallyImportedDimensions = [];
foreach ($definitions as $definition) {
    $dimensionCode = (string) ($definition['Code'] ?? '');
    $status = $statusByCode[$dimensionCode] ?? [];
    $label = (string) ($status['label'] ?? $definition['Label'] ?? $dimensionCode);
    $segmentNo = (int) ($status['mapping']['SegmentNo'] ?? 0);
    $sourceCount = (int) ($status['source_count'] ?? 0);
    $overlayCount = (int) ($status['overlay_count'] ?? 0);
    $missingCount = (int) ($status['missing_count'] ?? 0);

    if ($segmentNo <= 0) {
        $notMappedDimensions[] = $label;
        continue;
    }

    if ($sourceCount > 0 && $overlayCount <= 0) {
        $notImportedDimensions[] = $label;
        continue;
    }

    if ($missingCount > 0) {
        $partiallyImportedDimensions[] = $label;
    }
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-download me-2"></i>Import Dimensions</h3>
      <div class="d-flex flex-wrap gap-2">
        <a href="index.php?route=strategy-config/segment-mapping" class="btn btn-sm btn-outline-secondary">Segment Mapping</a>
        <a href="index.php?route=strategy/index" class="btn btn-sm btn-outline-secondary">Back to Overview</a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Fiscal Year scope:
        <strong><?= h($yearLabel !== '' ? $yearLabel : ($fiscalYearId > 0 ? (string) $fiscalYearId : 'Not set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">|</span>
          <span>Version context for target counts: <strong><?= h($versionLabel) ?></strong></span>
        <?php endif; ?>
      </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
      <?= h((string) ($flash['text'] ?? '')) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="alert alert-info border-0 shadow-sm mb-4">
    Each strategic dimension gets one primary card on this page. When a dimension is mapped and has a safe import path, the card gives you the import action. Otherwise it shows exactly why import is not available yet.
  </div>

  <?php if ($notImportedDimensions !== []): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4">
      <div class="fw-semibold mb-1">Import still needed</div>
      <div class="small">
        The following mapped dimensions have source values but no Strategy records yet:
        <strong><?= h(implode(', ', $notImportedDimensions)) ?></strong>.
        Import these before moving deeper into setup and planning.
      </div>
    </div>
  <?php endif; ?>

  <?php if ($partiallyImportedDimensions !== []): ?>
    <div class="alert alert-info border-0 shadow-sm mb-4">
      <div class="fw-semibold mb-1">Import partially complete</div>
      <div class="small">
        These dimensions still have source values that have not yet been created in Strategy:
        <strong><?= h(implode(', ', $partiallyImportedDimensions)) ?></strong>.
      </div>
    </div>
  <?php endif; ?>

  <?php if ($notMappedDimensions !== []): ?>
    <div class="alert alert-secondary border-0 shadow-sm mb-4">
      <div class="fw-semibold mb-1">Not mapped for source import</div>
      <div class="small">
        These dimensions are currently maintained directly in the strategic module unless you map them later:
        <strong><?= h(implode(', ', $notMappedDimensions)) ?></strong>.
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <?php foreach ($definitions as $definition): ?>
      <?php
        $dimensionCode = (string) ($definition['Code'] ?? '');
        $status = $statusByCode[$dimensionCode] ?? [
            'label' => (string) ($definition['Label'] ?? $dimensionCode),
            'mapping' => [],
            'source_count' => 0,
            'overlay_count' => 0,
            'overlay_label' => 'Current Records',
            'missing_count' => 0,
            'status_note' => 'No dashboard status is available for this dimension yet.',
            'import_supported' => false,
        ];
        $segmentNo = (int) ($status['mapping']['SegmentNo'] ?? 0);
        $sourceCount = (int) ($status['source_count'] ?? 0);
        $overlayCount = (int) ($status['overlay_count'] ?? 0);
        $missingCount = (int) ($status['missing_count'] ?? 0);
        $readinessBadge = ['label' => 'Ready', 'class' => 'bg-success-subtle text-success'];
        if ($segmentNo <= 0) {
            $readinessBadge = ['label' => 'Not Mapped', 'class' => 'bg-secondary-subtle text-secondary'];
        } elseif ($sourceCount > 0 && $overlayCount <= 0) {
            $readinessBadge = ['label' => 'Not Imported', 'class' => 'bg-warning-subtle text-warning'];
        } elseif ($missingCount > 0) {
            $readinessBadge = ['label' => 'Partially Imported', 'class' => 'bg-info-subtle text-info'];
        }
      ?>
      <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <h5 class="mb-1"><?= h((string) ($status['label'] ?? $dimensionCode)) ?></h5>
              <div class="small text-muted">
                <?php if ($segmentNo > 0): ?>
                  Segment <?= $segmentNo ?>
                <?php else: ?>
                  Not mapped
                <?php endif; ?>
              </div>
            </div>
            <span class="badge rounded-pill <?= h($readinessBadge['class']) ?>">
              <?= h($readinessBadge['label']) ?>
            </span>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-4">
                <div class="border rounded p-3 text-center">
                  <div class="small text-muted">Source</div>
                  <div class="fs-4 fw-semibold"><?= (int) ($status['source_count'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-4">
                <div class="border rounded p-3 text-center">
                  <div class="small text-muted"><?= h((string) ($status['overlay_label'] ?? 'Configured')) ?></div>
                  <div class="fs-4 fw-semibold"><?= (int) ($status['overlay_count'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-4">
                <div class="border rounded p-3 text-center">
                  <div class="small text-muted">Missing</div>
                  <div class="fs-4 fw-semibold"><?= (int) ($status['missing_count'] ?? 0) ?></div>
                </div>
              </div>
            </div>

            <?php if ($dimensionCode === 'SUBPROGRAM'): ?>
              <div class="row g-3 mb-3">
                <div class="col-4">
                  <div class="border rounded p-3 text-center">
                    <div class="small text-muted">Import Ready</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($status['import_ready_count'] ?? 0) ?></div>
                  </div>
                </div>
                <div class="col-4">
                  <div class="border rounded p-3 text-center">
                    <div class="small text-muted">Missing Parent Link</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($status['missing_parent_link_count'] ?? 0) ?></div>
                  </div>
                </div>
                <div class="col-4">
                  <div class="border rounded p-3 text-center">
                    <div class="small text-muted">Missing Parent Record</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($status['missing_parent_overlay_count'] ?? 0) ?></div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($dimensionCode === 'PROGRAM' && !empty($status['auto_sector_assignment'])): ?>
              <div class="row g-3 mb-3">
                <div class="col-4">
                  <div class="border rounded p-3 text-center">
                    <div class="small text-muted">Import Ready</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($status['import_ready_count'] ?? 0) ?></div>
                  </div>
                </div>
                <div class="col-4">
                  <div class="border rounded p-3 text-center">
                    <div class="small text-muted">Missing Sector Link</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($status['missing_sector_link_count'] ?? 0) ?></div>
                  </div>
                </div>
                <div class="col-4">
                  <div class="border rounded p-3 text-center">
                    <div class="small text-muted">Missing Sector Record</div>
                    <div class="fs-5 fw-semibold"><?= (int) ($status['missing_sector_overlay_count'] ?? 0) ?></div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <p class="small text-muted"><?= h((string) ($status['status_note'] ?? '')) ?></p>
            <?php if (!empty($status['last_import']) && is_array($status['last_import'])): ?>
              <div class="small text-muted mb-3">
                Last import:
                <strong><?= h((string) ($status['last_import']['Username'] ?? 'system')) ?></strong>
                on <?= h((string) date('d M Y H:i', strtotime((string) ($status['last_import']['EventTime'] ?? 'now')))) ?>
                <?php $summaryParts = is_array($status['last_import']['ImportSummaryParts'] ?? null) ? $status['last_import']['ImportSummaryParts'] : []; ?>
                <?php if ($summaryParts !== []): ?>
                  <span class="d-block mt-2"><?= renderImportSummaryPills($summaryParts) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($dimensionCode === 'PROGRAM'): ?>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <?php if (!empty($status['manage_route'])): ?>
                  <a href="index.php?route=<?= h((string) $status['manage_route']) ?>" class="btn btn-outline-primary btn-sm">
                    <?= h((string) ($status['manage_label'] ?? 'Open')) ?>
                  </a>
                <?php endif; ?>
              </div>
              <?php if (!empty($status['requires_sector_selection'])): ?>
                <form method="post" action="index.php?route=strategy-setup/import-program-overlays" class="row g-2 align-items-end js-strategy-import-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
                  <div class="col-md-8">
                    <label class="form-label">Sector for New Program Overlays</label>
                    <select name="sector_id" class="form-select" required>
                      <option value="">Select sector</option>
                      <?php foreach (($sectorOptions ?? []) as $option): ?>
                        <option value="<?= (int) $option['SectorID'] ?>"><?= h((string) $option['SectorName']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-4 d-grid">
                    <button type="button" class="btn btn-success btn-sm js-strategy-import-trigger" data-confirm-title="Import Program Records" data-confirm-message="Create the missing program records in Strategy and assign them to the selected sector?">
                      Import
                    </button>
                  </div>
                </form>
              <?php else: ?>
                <form method="post" action="index.php?route=strategy-setup/import-program-overlays" class="js-strategy-import-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
                  <button type="button" class="btn btn-success btn-sm js-strategy-import-trigger" data-confirm-title="Import Program Records" data-confirm-message="Create the missing program records in Strategy and auto-assign sectors from the mapped source links?">
                    Import
                  </button>
                </form>
              <?php endif; ?>
            <?php elseif ($dimensionCode === 'FUNDING_SOURCE'): ?>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <?php if (!empty($status['manage_route'])): ?>
                  <a href="index.php?route=<?= h((string) $status['manage_route']) ?>" class="btn btn-outline-primary btn-sm">
                    <?= h((string) ($status['manage_label'] ?? 'Open')) ?>
                  </a>
                <?php endif; ?>
                <form method="post" action="index.php?route=strategy-setup/import-funding-source-overlays" class="d-flex flex-wrap gap-2 align-items-center js-strategy-import-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="default_funding_type_code" value="DOMESTIC">
                  <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
                  <button type="button" class="btn btn-success btn-sm js-strategy-import-trigger" data-confirm-title="Import Funding Source Records" data-confirm-message="Create the missing funding source records in Strategy using DOMESTIC as the default type?">
                    Import
                  </button>
                </form>
              </div>
              <div class="small text-muted">Default type for new Strategy records: <strong>DOMESTIC</strong></div>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-2">
                <?php if (!empty($status['manage_route'])): ?>
                  <a href="index.php?route=<?= h((string) $status['manage_route']) ?>" class="btn btn-outline-primary btn-sm">
                    <?= h((string) ($status['manage_label'] ?? 'Open')) ?>
                  </a>
                <?php endif; ?>

                <?php if (!empty($status['import_supported']) && !empty($status['import_action'])): ?>
                  <form method="post" action="index.php?route=<?= h((string) $status['import_action']) ?>" class="js-strategy-import-form">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
                    <button type="button" class="btn btn-success btn-sm js-strategy-import-trigger" data-confirm-title="<?= h((string) ($status['import_confirm_title'] ?? 'Confirm Import')) ?>" data-confirm-message="<?= h((string) ($status['import_confirm_message'] ?? 'Proceed with this import?')) ?>">
                      Import
                    </button>
                  </form>
                <?php elseif (!empty($status['import_supported'])): ?>
                  <button type="button" class="btn btn-success btn-sm" disabled>Import Available Here</button>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm" disabled>Import Not Available</button>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($status['import_reason'])): ?>
              <div class="small text-muted mt-2"><?= h((string) $status['import_reason']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
  <?php endforeach; ?>
</div>
</div>
</div>
</div>

<div class="modal fade" id="strategyImportConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="strategyImportConfirmModalLabel">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="strategyImportConfirmModalLabel">Confirm Import</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3" id="strategyImportConfirmModalText">Proceed with this import?</p>
        <div class="mb-0">
          <label for="strategyImportResetMode" class="form-label small text-muted mb-1">Import Mode</label>
          <select id="strategyImportResetMode" class="form-select form-select-sm">
            <option value="none">Import new and changed only</option>
            <option value="soft">Soft reset current fiscal year imports, then reimport</option>
            <option value="hard">Hard reset current fiscal year imports, then reimport</option>
          </select>
          <div class="form-text">Reset modes only apply to current fiscal year imported Strategy records. In-use records are preserved.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="strategyImportConfirmSubmit">Continue</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('strategyImportConfirmModal');
  if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
    return;
  }

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
    backdrop: 'static',
    keyboard: true
  });
  const titleEl = document.getElementById('strategyImportConfirmModalLabel');
  const textEl = document.getElementById('strategyImportConfirmModalText');
  const confirmBtn = document.getElementById('strategyImportConfirmSubmit');
  const resetModeSelect = document.getElementById('strategyImportResetMode');
  let pendingForm = null;
  const showProgressOverlay = () => {
    let overlay = document.getElementById('strategyImportProgressOverlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'strategyImportProgressOverlay';
      overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
      overlay.style.cssText = 'background: rgba(255,255,255,.72); z-index: 1080;';
      overlay.innerHTML = '<div class="bg-white border rounded shadow-sm px-4 py-3 small text-muted"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Import in progress. Please wait...</div>';
      document.body.appendChild(overlay);
    }
  };

  document.querySelectorAll('.js-strategy-import-trigger').forEach((button) => {
    button.addEventListener('click', () => {
      pendingForm = button.closest('form');
      if (!pendingForm) {
        return;
      }

      if (!pendingForm.reportValidity()) {
        pendingForm = null;
        return;
      }

      titleEl.textContent = button.getAttribute('data-confirm-title') || 'Confirm Import';
      textEl.textContent = button.getAttribute('data-confirm-message') || 'Proceed with this import?';
      modal.show();
    });
  });

  confirmBtn.addEventListener('click', () => {
    if (!pendingForm) {
      modal.hide();
      return;
    }
    const form = pendingForm;
    const resetModeField = form.querySelector('.js-strategy-reset-mode');
    if (resetModeField && resetModeSelect) {
      resetModeField.value = resetModeSelect.value || 'none';
    }
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Importing...';
    pendingForm = null;
    modal.hide();
    showProgressOverlay();
    window.setTimeout(() => form.submit(), 50);
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    pendingForm = null;
    confirmBtn.disabled = false;
    confirmBtn.textContent = 'Continue';
    if (resetModeSelect) {
      resetModeSelect.value = 'none';
    }
  });
});
</script>
