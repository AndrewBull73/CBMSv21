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
    $segmentLabel = 'Mapped source segment ' . (int) $segmentMapping['SegmentNo'];
}
$scope = in_array((string) ($scope ?? 'current_imported'), ['current_imported', 'all'], true)
    ? (string) $scope
    : 'current_imported';
$lastImport = is_array($lastImport ?? null) ? $lastImport : null;
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$screenHeader = [
    'title' => 'Project Register',
    'icon' => 'bi-kanban',
];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-2">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>
      <?php if ($segmentLabel !== ''): ?>
        <div class="small text-muted mb-3">
          Source mapping:
          <strong><?= h($segmentLabel) ?></strong>
        </div>
      <?php endif; ?>
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div class="small text-muted">
          Maintain reusable strategic project masters and keep current-year imported scope easy to review, clean up, and recode before broader planning work begins.
        </div>
        <a href="index.php?route=strategy-setup/project-form" id="project-new-btn" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-circle me-1"></i>New Project
        </a>
      </div>

      <div class="alert alert-info border-0 shadow-sm">
        Projects are reusable strategic master records. By default this register shows the current fiscal year imported project scope so bad imports can be cleaned up more easily.
      </div>

      <form method="get" action="index.php" class="row g-2 mb-3" id="project-filter-form">
        <input type="hidden" name="route" value="strategy-setup/projects">
        <div class="col-md-5">
          <input type="text" name="q" id="project-search-input" value="<?= h((string) ($q ?? '')) ?>" class="form-control form-control-sm" placeholder="Search by code, name, description, or lead org unit">
        </div>
        <div class="col-md-4">
          <select name="scope" id="project-scope-select" class="form-select form-select-sm">
            <option value="current_imported" <?= $scope === 'current_imported' ? 'selected' : '' ?>>Current FY Imported Only</option>
            <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All Active Project Masters</option>
          </select>
        </div>
        <div class="col-md-3 d-grid">
          <button type="submit" id="project-filter-btn" class="btn btn-sm btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Use import only for new or changed segment values, or choose a current-year soft or hard reset when you need to rebuild imported project records.</div>
        <form method="post" action="index.php?route=strategy-setup/import-project-overlays" class="js-strategy-import-form" id="project-import-form">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode" id="project-import-reset-mode">
          <button type="button" id="project-import-btn" class="btn btn-sm btn-outline-success js-strategy-import-trigger" data-confirm-title="Import Projects" data-confirm-message="Create missing project records and source mappings from the configured project segment?">
            <i class="bi bi-download me-1"></i>Import From Segment
          </button>
        </form>
      </div>
      <?php if ($lastImport !== null): ?>
        <?php $summaryText = trim((string) ($lastImport['ImportSummaryText'] ?? '')); ?>
        <div class="small text-muted mb-3">Last import: <strong><?= h((string) ($lastImport['Username'] ?? 'system')) ?></strong> on <?= h((string) date('d M Y H:i', strtotime((string) ($lastImport['EventTime'] ?? 'now')))) ?><?php if ($summaryText !== ''): ?><span class="d-block">Summary: <?= h($summaryText) ?></span><?php endif; ?></div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle" id="project-register-table">
          <thead class="table-light">
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Type</th>
              <th>Status</th>
              <th>Lead Org</th>
              <th class="text-center">Programs</th>
              <th class="text-center">Objectives</th>
              <th class="text-center">Mappings</th>
              <th class="text-center">Funding</th>
              <th class="text-center">Active</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($records)): ?>
              <tr><td colspan="11" class="text-center text-muted py-4">No projects found.</td></tr>
            <?php else: ?>
              <?php foreach ($records as $row): ?>
                <tr>
                  <td><?= h((string) ($row['ProjectCode'] ?? '')) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['ProjectName'] ?? '')) ?></div>
                    <?php if (!empty($row['ProjectDescription'])): ?>
                      <div class="small text-muted"><?= h(strlen((string) $row['ProjectDescription']) > 120 ? substr((string) $row['ProjectDescription'], 0, 117) . '...' : (string) $row['ProjectDescription']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string) ($row['ProjectTypeCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['LifecycleStatusCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['LeadOrgUnitName'] ?? '')) ?></td>
                  <td class="text-center"><?= (int) ($row['ProgramLinkCount'] ?? 0) ?></td>
                  <td class="text-center"><?= (int) ($row['ObjectiveLinkCount'] ?? 0) ?></td>
                  <td class="text-center"><?= (int) ($row['SourceMappingCount'] ?? 0) ?></td>
                  <td class="text-center"><?= (int) ($row['FundingSubmissionCount'] ?? 0) ?></td>
                  <td class="text-center">
                    <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                      <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Archived' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <a href="index.php?route=strategy-setup/project-usage&id=<?= (int) ($row['ProjectID'] ?? 0) ?>" id="project-usage-btn-<?= (int) ($row['ProjectID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary" title="View usage">
                        <i class="bi bi-diagram-3"></i>
                      </a>
                      <a href="index.php?route=strategy-setup/project-form&id=<?= (int) ($row['ProjectID'] ?? 0) ?>" id="project-edit-btn-<?= (int) ($row['ProjectID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary" title="Edit project">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <?php if ((int) ($row['ActiveFlag'] ?? 0) === 1): ?>
                        <form method="post" action="index.php?route=strategy-setup/delete-project" id="project-archive-form-<?= (int) ($row['ProjectID'] ?? 0) ?>" onsubmit="return confirm('Archive this project?');">
                          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                          <input type="hidden" name="id" value="<?= (int) ($row['ProjectID'] ?? 0) ?>">
                          <button type="submit" id="project-archive-btn-<?= (int) ($row['ProjectID'] ?? 0) ?>" class="btn btn-sm btn-outline-danger" title="Archive project">
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
          <div class="form-text">
            Soft reset archives removable imported records for the current fiscal year. Hard reset permanently deletes removable imported records for the current fiscal year. In-use projects are preserved.
          </div>
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
  if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) { return; }
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
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
      if (!pendingForm) { return; }
      titleEl.textContent = button.getAttribute('data-confirm-title') || 'Confirm Import';
      textEl.textContent = button.getAttribute('data-confirm-message') || 'Proceed with this import?';
      modal.show();
    });
  });

  confirmBtn.addEventListener('click', () => {
    if (!pendingForm) { modal.hide(); return; }
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
