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
      <h3 class="mb-0"><i class="bi bi-list-task me-2"></i>Strategic Activities</h3>
      <div class="d-flex align-items-center gap-2">
        <?php if ($segmentLabel !== ''): ?><span class="small text-muted"><?= h($segmentLabel) ?></span><?php endif; ?>
        <a href="index.php?route=strategy-delivery/activity-form" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-circle me-1"></i>Create Activity
        </a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="strategy-delivery/activities">
        <div class="col-md-5">
          <input type="text" name="q" value="<?= h((string) ($q ?? '')) ?>" class="form-control" placeholder="Search activities">
        </div>
        <div class="col-md-5">
          <select name="output_id" class="form-select">
            <option value="">All outputs</option>
            <?php foreach (($outputOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['OutputID'] ?>" <?= ((int) ($outputId ?? 0) === (int) $option['OutputID']) ? 'selected' : '' ?>>
                <?= h((string) $option['ProgramName'] . ' / ' . $option['OutputName']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Bulk import creates missing activity records from the mapped segment values where the parent output can be resolved.</div>
        <form method="post" action="index.php?route=strategy-delivery/import-activity-overlays" class="js-strategy-import-form">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
          <button type="button" class="btn btn-sm btn-outline-success js-strategy-import-trigger" data-confirm-title="Import Activities" data-confirm-message="Create missing activity records from the mapped source segment values where each row links to an imported output?">
            <i class="bi bi-download me-1"></i>Import Dimensions
          </button>
        </form>
      </div>
      <?php if ($lastImport !== null): ?>
        <?php $summaryText = trim((string) ($lastImport['ImportSummaryText'] ?? '')); ?>
        <div class="small text-muted mb-3">Last import: <strong><?= h((string) ($lastImport['Username'] ?? 'system')) ?></strong> on <?= h((string) date('d M Y H:i', strtotime((string) ($lastImport['EventTime'] ?? 'now')))) ?><?php if ($summaryText !== ''): ?><span class="d-block">Summary: <?= h($summaryText) ?></span><?php endif; ?></div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Activity</th>
              <th>Project</th>
              <th>Program</th>
              <th>Output</th>
              <th>Type</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No activities found.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['ActivityName'] ?? '')) ?></div>
                  <?php if (!empty($row['LocationCode'])): ?>
                    <div class="small text-muted"><?= h((string) $row['LocationCode']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($row['ProjectName'])): ?>
                    <div class="fw-semibold"><?= h((string) ($row['ProjectName'] ?? '')) ?></div>
                    <?php if (!empty($row['ProjectCode'])): ?><div class="small text-muted"><?= h((string) ($row['ProjectCode'] ?? '')) ?></div><?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted">Not linked</span>
                  <?php endif; ?>
                </td>
                <td><?= h((string) ($row['ProgramName'] ?? '')) ?></td>
                <td><?= h((string) ($row['OutputName'] ?? '')) ?></td>
                <td><?= h((string) ($row['ActivityTypeCode'] ?? '')) ?></td>
                <td>
                  <div class="small"><?= h((string) ($row['ImplementationStatusCode'] ?? '')) ?></div>
                  <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Archived' ?></span>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a href="index.php?route=strategy-delivery/activity-form&id=<?= (int) ($row['ActivityID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                    <?php if ((int) ($row['ActiveFlag'] ?? 0) === 1): ?>
                      <form method="post" action="index.php?route=strategy-delivery/delete-activity" onsubmit="return confirm('Archive this activity?');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="id" value="<?= (int) ($row['ActivityID'] ?? 0) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
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
