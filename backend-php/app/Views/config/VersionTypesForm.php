<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$versionTypeId = (int) ($record['VersionTypeID'] ?? 0);
$screenHeader = [
    'title' => $versionTypeId > 0 ? 'Edit Version Type' : 'Create Version Type',
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

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this form to create or refine one version type in the shared catalogue. Keep the code stable because existing versions, readiness checks, and downstream logic rely on it.
      </div>

      <form method="post" action="index.php?route=version-types/save" id="version-types-form">
        <?= csrf_field() ?>
        <input type="hidden" name="VersionTypeID" value="<?= $versionTypeId ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Identity</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="versionTypeCode">Code</label>
                <input type="text" class="form-control" id="versionTypeCode" name="VersionTypeCode" value="<?= h((string) ($record['VersionTypeCode'] ?? '')) ?>" maxlength="30" required>
                <div class="form-text">Use a stable uppercase code such as <code>SUBMISSION</code> or <code>EXECUTION</code>.</div>
              </div>
              <div class="col-md-8">
                <label class="form-label" for="versionTypeName">Name</label>
                <input type="text" class="form-control" id="versionTypeName" name="VersionTypeName" value="<?= h((string) ($record['VersionTypeName'] ?? '')) ?>" maxlength="100" required>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Description And Status</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="versionTypeDescription">Description</label>
                <textarea class="form-control" id="versionTypeDescription" name="Description" rows="3" maxlength="255"><?= h((string) ($record['Description'] ?? '')) ?></textarea>
              </div>
              <div class="col-md-4">
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="versionTypeActiveFlag" name="ActiveFlag" value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="versionTypeActiveFlag">Active</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <a id="version-types-back-btn" href="index.php?route=version-types/list" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="version-types-save-btn" type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
