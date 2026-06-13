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
$dataObjectTypeId = (int) ($record['DataObjectTypeID'] ?? 0);
$screenHeader = [
    'title' => $dataObjectTypeId > 0 ? 'Edit Data Object Type' : 'Create Data Object Type',
    'icon' => 'bi-diagram-3',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this form to create or refine one data object type in the shared organisational structure catalogue. Level and container settings influence how parent-child relationships are maintained on the Data Object Codes screen, so change them carefully.
      </div>

      <form method="post" action="index.php?route=dataobject-types/save" id="data-object-types-form">
        <?= csrf_field() ?>
        <input type="hidden" name="DataObjectTypeID" value="<?= $dataObjectTypeId ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Identity</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <?php if ($dataObjectTypeId > 0): ?>
                <div class="col-md-3">
                  <label class="form-label" for="dataObjectTypeId">Type ID</label>
                  <input type="text" class="form-control" id="dataObjectTypeId" value="<?= $dataObjectTypeId ?>" readonly>
                </div>
              <?php else: ?>
                <div class="col-12">
                  <div class="form-text mb-2">Type ID will be assigned automatically when you save the new record.</div>
                </div>
              <?php endif; ?>
              <div class="col-md-<?= $dataObjectTypeId > 0 ? '9' : '12' ?>">
                <label class="form-label" for="dataObjectTypeName">Type Name</label>
                <input type="text" class="form-control" id="dataObjectTypeName" name="DataObjectTypeName" value="<?= h((string) ($record['DataObjectTypeName'] ?? '')) ?>" maxlength="100" required>
                <div class="form-text">Use a stable organisational type name such as <code>Government</code>, <code>Head</code>, or <code>Project</code>.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Hierarchy And Source Mapping</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="dataObjectTypeLevel">Level</label>
                <input type="number" min="1" max="255" class="form-control" id="dataObjectTypeLevel" name="Level" value="<?= h((string) ($record['Level'] ?? '')) ?>" required>
                <div class="form-text">Use lower numbers for higher organisational levels. Parent selection on Data Object Codes depends on this sequence.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="dataObjectTypeSegmentNo">Segment No</label>
                <input type="number" min="1" class="form-control" id="dataObjectTypeSegmentNo" name="SegmentNo" value="<?= h((string) ($record['SegmentNo'] ?? '')) ?>">
                <div class="form-text">Optional source segment number if this type aligns to one source segment in the client structure.</div>
              </div>
              <div class="col-md-4">
                <div class="form-check mt-4 pt-2">
                  <input class="form-check-input" type="checkbox" id="dataObjectTypeContainer" name="DataContainer" value="1" <?= ((int) ($record['DataContainer'] ?? 1) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="dataObjectTypeContainer">This type can act as a container</label>
                </div>
                <div class="form-text">Leave this checked for organisational nodes that can hold child Data Object Codes. Clear it for terminal or leaf-level types.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <a id="data-object-types-back-btn" href="index.php?route=dataobject-types/list" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="data-object-types-save-btn" type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
