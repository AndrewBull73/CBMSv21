<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
$segmentLabel = '';
if (!empty($segmentMapping['SegmentNo'])) {
    $segmentLabel = 'Mapped from segment ' . (int) $segmentMapping['SegmentNo'];
}
$lastImport = is_array($lastImport ?? null) ? $lastImport : null;
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="mb-0"><i class="bi bi-kanban me-2"></i>Strategic Programs</h3>
      <?php if ($segmentLabel !== ''): ?>
        <span class="small text-muted"><?= h($segmentLabel) ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="alert alert-info">
        Program source values are read from the configured segment mapping. Sector and manager are maintained here as Strategy records.
      </div>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="strategy-setup/programs">
        <div class="col-md-4">
          <input type="text" name="q" value="<?= h((string) ($q ?? '')) ?>" class="form-control" placeholder="Search programs">
        </div>
        <div class="col-md-3">
          <select name="sector_id" class="form-select">
            <option value="">All sectors</option>
            <?php foreach (($sectorOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['SectorID'] ?>" <?= ((int) ($sectorId ?? 0) === (int) $option['SectorID']) ? 'selected' : '' ?>>
                <?= h((string) $option['SectorName']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">
          Bulk import creates missing program records from the mapped segment values for the active fiscal year.
        </div>
        <form method="post" action="index.php?route=strategy-setup/import-program-overlays" class="d-inline-flex gap-2 js-strategy-import-form">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="sector_id" value="<?= (int) ($sectorId ?? 0) ?>">
          <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
          <button type="button" class="btn btn-sm btn-outline-success js-strategy-import-trigger" <?= ((int) ($sectorId ?? 0) > 0) ? '' : 'disabled' ?> data-confirm-title="Import Programs" data-confirm-message="Create missing program records from the mapped source segment values for the selected sector?">
            <i class="bi bi-download me-1"></i>Import Dimensions
          </button>
        </form>
      </div>
      <?php if ($lastImport !== null): ?>
        <?php $summaryText = trim((string) ($lastImport['ImportSummaryText'] ?? '')); ?>
        <div class="small text-muted mb-3">Last import: <strong><?= h((string) ($lastImport['Username'] ?? 'system')) ?></strong> on <?= h((string) date('d M Y H:i', strtotime((string) ($lastImport['EventTime'] ?? 'now')))) ?><?php if ($summaryText !== ''): ?><span class="d-block">Summary: <?= h($summaryText) ?></span><?php endif; ?></div>
      <?php endif; ?>

      <?php if ((int) ($sectorId ?? 0) <= 0): ?>
        <div class="alert alert-warning py-2">
          Select a sector first. Imported program records need a sector assignment.
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Program</th>
              <th>Sector</th>
              <th>Owner DataScope Org Unit</th>
              <th>Manager</th>
              <th>Overlay</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No programs found.</td></tr>
            <?php else: ?>
              <?php foreach ($records as $row): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                    <?php if (!empty($row['ProgramCode'])): ?>
                      <div class="small text-muted"><?= h((string) $row['ProgramCode']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                  <td>
                    <div><?= h((string) ($row['OrgUnitName'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['OrgUnitDataObjectCode'] ?? '')) ?></div>
                  </td>
                  <td><?= h((string) ($row['ProgramManagerName'] ?? '')) ?></td>
                  <td>
                    <span class="badge text-bg-<?= ((int) ($row['OverlayConfiguredFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                      <?= ((int) ($row['OverlayConfiguredFlag'] ?? 0) === 1) ? 'Configured' : 'Not Configured' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <?php if ((int) ($row['ProgramID'] ?? 0) > 0): ?>
                        <a href="index.php?route=strategy-setup/program-form&id=<?= (int) ($row['ProgramID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary" title="Edit record">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      <?php else: ?>
                        <a href="index.php?route=strategy-setup/program-form&data_object_code=<?= urlencode((string) ($row['OrgUnitDataObjectCode'] ?? '')) ?>&program_code=<?= urlencode((string) ($row['ProgramCode'] ?? '')) ?>" class="btn btn-sm btn-outline-primary" title="Configure record">
                          <i class="bi bi-sliders"></i>
                        </a>
                      <?php endif; ?>
                      <?php if ((int) ($row['ProgramID'] ?? 0) > 0 && (int) ($row['ActiveFlag'] ?? 0) === 1): ?>
                        <form method="post" action="index.php?route=strategy-setup/delete-program" onsubmit="return confirm('Archive this program record?');">
                          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                          <input type="hidden" name="id" value="<?= (int) ($row['ProgramID'] ?? 0) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-archive"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
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
