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
$records = is_array($records ?? null) ? $records : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$segmentNo = (int) ($segmentMapping['SegmentNo'] ?? 0);
$segmentLabel = $segmentNo > 0 ? 'Mapped from segment ' . $segmentNo : '';
$lastImport = is_array($lastImport ?? null) ? $lastImport : null;
$activeCount = 0;
$programs = [];
$subPrograms = [];
$goalLinkedCount = 0;

foreach ($records as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    }
    $programName = trim((string) ($row['ProgramName'] ?? ''));
    if ($programName !== '') {
        $programs[$programName] = true;
    }
    $subProgramName = trim((string) ($row['SubProgramName'] ?? ''));
    if ($subProgramName !== '') {
        $subPrograms[$subProgramName] = true;
    }
    if (trim((string) ($row['GoalNames'] ?? '')) !== '') {
        $goalLinkedCount++;
    }
}

$screenHeader = [
    'title' => 'Strategic Objectives',
    'icon' => 'bi-bullseye',
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

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Objectives in register</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active objectives</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Programs represented</div>
              <div class="fs-4 fw-semibold"><?= count($programs) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Rows linked to goals</div>
              <div class="fs-4 fw-semibold"><?= $goalLinkedCount ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to maintain program-linked strategic objectives. Where dimension mapping is active, import can create missing objective records from source segments before manual refinement.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Search and Actions</h5>
          <div class="d-flex flex-wrap gap-2">
            <?php if ($segmentNo > 0): ?>
              <form method="post" action="index.php?route=strategy-performance/import-objective-overlays" class="js-strategy-import-form mb-0">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="reset_mode" value="none" class="js-strategy-reset-mode">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary js-strategy-import-trigger"
                  data-confirm-title="Import Objectives"
                  data-confirm-message="Create missing objective records where each source row links to an imported program or subprogram?"
                >
                  <i class="bi bi-download me-1"></i>Import Dimensions
                </button>
              </form>
            <?php endif; ?>
            <a href="index.php?route=strategy-performance/objective-form" class="btn btn-sm btn-primary">
              <i class="bi bi-plus-circle me-1"></i>Create Objective
            </a>
          </div>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2 mb-3">
            <input type="hidden" name="route" value="strategy-performance/objectives">
            <div class="col-md-5">
              <input
                type="text"
                name="q"
                value="<?= h((string) ($q ?? '')) ?>"
                class="form-control"
                placeholder="Search objectives"
              >
            </div>
            <div class="col-md-5">
              <select name="program_id" class="form-select">
                <option value="">All programs</option>
                <?php foreach (($programOptions ?? []) as $option): ?>
                  <option value="<?= (int) $option['ProgramID'] ?>" <?= ((int) ($programId ?? 0) === (int) $option['ProgramID']) ? 'selected' : '' ?>>
                    <?= h((string) $option['ProgramName']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
          </form>

          <div class="small text-muted">
            <?php if ($segmentLabel !== ''): ?>
              <div><strong>Import mapping:</strong> <?= h($segmentLabel) ?></div>
            <?php else: ?>
              <div><strong>Import mapping:</strong> Objective import is not available until segment mapping is configured.</div>
            <?php endif; ?>
            <?php if ($lastImport !== null): ?>
              <?php $summaryText = trim((string) ($lastImport['ImportSummaryText'] ?? '')); ?>
              <div class="mt-1">
                <strong>Last import:</strong>
                <?= h((string) ($lastImport['Username'] ?? 'system')) ?>
                on <?= h((string) date('d M Y H:i', strtotime((string) ($lastImport['EventTime'] ?? 'now')))) ?>
                <?php if ($summaryText !== ''): ?>
                  <span class="d-block">Summary: <?= h($summaryText) ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="mt-1">
              <strong>Subprogram coverage:</strong> <?= count($subPrograms) ?> subprograms represented in the filtered register.
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Objective Register</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Objective</th>
                  <th>Program</th>
                  <th>SubProgram</th>
                  <th>Goal</th>
                  <th class="text-end">Priority</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($records === []): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-3">No objectives found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $row): ?>
                  <?php $isActive = (int) ($row['ActiveFlag'] ?? 0) === 1; ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ObjectiveText'] ?? '')) ?></div>
                      <?php if (!empty($row['PolicyLink'])): ?>
                        <div class="small text-muted"><?= h((string) $row['PolicyLink']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['ProgramName'] ?? '')) ?></td>
                    <td><?= h((string) ($row['SubProgramName'] ?? '')) ?></td>
                    <td><div class="small"><?= h((string) ($row['GoalNames'] ?? '')) ?></div></td>
                    <td class="text-end"><?= h((string) ($row['PriorityRank'] ?? '')) ?></td>
                    <td>
                      <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
                        <?= $isActive ? 'Active' : 'Archived' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <a
                          href="index.php?route=strategy-performance/objective-form&id=<?= (int) ($row['ObjectiveID'] ?? 0) ?>"
                          class="btn btn-outline-primary btn-sm"
                        >
                          Edit
                        </a>
                        <?php if ($isActive): ?>
                          <form method="post" action="index.php?route=strategy-performance/delete-objective" onsubmit="return confirm('Archive this objective?');">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= (int) ($row['ObjectiveID'] ?? 0) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Archive</button>
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
