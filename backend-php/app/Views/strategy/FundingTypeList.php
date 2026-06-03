<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$csrf = h(csrf_token());
$segmentLabel = '';
if (!empty($segmentMapping['SegmentNo'])) {
    $segmentLabel = 'Mapped from segment ' . (int) $segmentMapping['SegmentNo'];
}
$lastImport = is_array($lastImport ?? null) ? $lastImport : null;
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header d-flex justify-content-between align-items-center"><h3 class="mb-0"><i class="bi bi-collection me-2"></i>Strategic Funding Types</h3><?php if ($segmentLabel !== ''): ?><span class="small text-muted"><?= h($segmentLabel) ?></span><?php endif; ?></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="alert alert-info">Funding types now have their own standalone strategic table. When a client maps a funding type segment, you can import the source values as Strategy records; otherwise you can maintain them directly here.</div>
<form method="get" action="index.php" class="row g-2 mb-3"><input type="hidden" name="route" value="strategy-setup/funding-types"><div class="col-md-8"><input type="text" name="q" value="<?= h((string)($q ?? '')) ?>" class="form-control" placeholder="Search funding types"></div><div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Filter</button></div></form>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div class="small text-muted">Bulk import creates missing funding type records from the mapped segment values.</div><div class="d-flex gap-2"><a href="index.php?route=strategy-setup/funding-type-form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Funding Type</a><?php if (!empty($segmentMapping['SegmentNo'])): ?><form method="post" action="index.php?route=strategy-setup/import-funding-type-overlays" class="js-strategy-import-form"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode"><button type="button" class="btn btn-sm btn-outline-success js-strategy-import-trigger" data-confirm-title="Import Funding Types" data-confirm-message="Create missing funding type records from the mapped source segment values?"><i class="bi bi-download me-1"></i>Import Dimensions</button></form><?php else: ?><button type="button" class="btn btn-sm btn-outline-secondary" disabled>Import Not Available</button><?php endif; ?></div></div><?php if ($lastImport !== null): ?><?php $summaryText = trim((string) ($lastImport['ImportSummaryText'] ?? '')); ?><div class="small text-muted mb-3">Last import: <strong><?= h((string) ($lastImport['Username'] ?? 'system')) ?></strong> on <?= h((string) date('d M Y H:i', strtotime((string) ($lastImport['EventTime'] ?? 'now')))) ?><?php if ($summaryText !== ''): ?><span class="d-block">Summary: <?= h($summaryText) ?></span><?php endif; ?></div><?php endif; ?>
<?php if (!empty($orphanRecords)): ?><div class="alert alert-warning">There are <?= (int) count($orphanRecords) ?> active funding type record(s) that do not belong to the current mapped segment values. They are shown below in a separate cleanup section so you can archive them.</div><?php endif; ?>
<div class="table-responsive"><table class="table table-striped table-hover align-middle"><thead class="table-light"><tr><th>Code</th><th>Name</th><th>Description</th><th>Overlay</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if (empty($records)): ?><tr><td colspan="5" class="text-center text-muted py-3">No funding types found.</td></tr><?php else: foreach ($records as $row): ?><tr><td><div><?= h((string)($row['FundingTypeCode'] ?? $row['SourceSegmentCode'] ?? '')) ?></div><?php if (!empty($row['SourceSegmentCode'])): ?><div class="small text-muted"><?= h((string)$row['SourceSegmentCode']) ?></div><?php endif; ?></td><td><?= h((string)($row['FundingTypeName'] ?? '')) ?></td><td><?= h((string)($row['FundingTypeDescription'] ?? '')) ?></td><td><span class="badge text-bg-<?= ((int)($row['OverlayConfiguredFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int)($row['OverlayConfiguredFlag'] ?? 0) === 1) ? 'Configured' : 'Not Configured' ?></span></td><td class="text-end"><div class="d-inline-flex gap-1"><?php if ((int)($row['FundingTypeID'] ?? 0) > 0): ?><a href="index.php?route=strategy-setup/funding-type-form&id=<?= (int)($row['FundingTypeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a><?php else: ?><a href="index.php?route=strategy-setup/funding-type-form&source_segment_code=<?= urlencode((string)($row['SourceSegmentCode'] ?? '')) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders"></i></a><?php endif; ?><?php if ((int)($row['FundingTypeID'] ?? 0) > 0 && (int)($row['ActiveFlag'] ?? 0) === 1): ?><form method="post" action="index.php?route=strategy-setup/delete-funding-type" onsubmit="return confirm('Archive this funding type record?');"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= (int)($row['FundingTypeID'] ?? 0) ?>"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button></form><?php endif; ?></div></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div></div></div>
<?php if (!empty($orphanRecords)): ?><div class="container mt-4"><div class="card shadow-sm border-warning"><div class="card-header bg-warning-subtle"><h5 class="mb-0">Orphaned / Old Funding Type Records</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover align-middle mb-0"><thead class="table-light"><tr><th>Code</th><th>Name</th><th>Reason</th><th class="text-end">Actions</th></tr></thead><tbody><?php foreach ($orphanRecords as $row): ?><tr><td><div><?= h((string)($row['FundingTypeCode'] ?? '')) ?></div><?php if (!empty($row['SourceSegmentCode'])): ?><div class="small text-muted"><?= h((string)$row['SourceSegmentCode']) ?></div><?php endif; ?></td><td><?= h((string)($row['FundingTypeName'] ?? '')) ?></td><td><?= h((string)($row['OrphanReason'] ?? '')) ?></td><td class="text-end"><div class="d-inline-flex gap-1"><a href="index.php?route=strategy-setup/funding-type-form&id=<?= (int)($row['FundingTypeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a><form method="post" action="index.php?route=strategy-setup/delete-funding-type" onsubmit="return confirm('Archive this orphaned funding type record?');"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= (int)($row['FundingTypeID'] ?? 0) ?>"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button></form></div></td></tr><?php endforeach; ?></tbody></table></div></div></div></div><?php endif; ?>

<div class="modal fade" id="strategyImportConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="strategyImportConfirmModalLabel">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="strategyImportConfirmModalLabel">Confirm Import</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3" id="strategyImportConfirmModalText">Proceed with this import?</p>
        <div class="mb-0"><label for="strategyImportResetMode" class="form-label small text-muted mb-1">Import Mode</label><select id="strategyImportResetMode" class="form-select form-select-sm"><option value="none">Import new and changed only</option><option value="soft">Soft reset current fiscal year imports, then reimport</option><option value="hard">Hard reset current fiscal year imports, then reimport</option></select><div class="form-text">Reset modes only apply to current fiscal year imported Strategy records. In-use records are preserved.</div></div>
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
