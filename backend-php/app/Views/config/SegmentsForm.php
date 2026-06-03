<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$dimensions = is_array($dimensions ?? null) ? $dimensions : [];
$groups = is_array($groups ?? null) ? $groups : [];
$id = (int) ($record['SegmentID'] ?? 0);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Segment' : 'Create Segment' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=segments/save" id="segments-form">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label">Segment ID</label>
          <input id="segmentId" class="form-control" type="number" min="1" name="SegmentID" value="<?= h((string) ($record['SegmentID'] ?? '')) ?>" <?= $id > 0 ? 'readonly' : 'required' ?>>
        </div>

        <div class="mb-3">
          <label class="form-label">Segment Code</label>
          <input id="segmentCode" class="form-control" type="text" name="SegmentCode" value="<?= h((string) ($record['SegmentCode'] ?? '')) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Segment Name</label>
          <input id="segmentName" class="form-control" type="text" name="SegmentName" value="<?= h((string) ($record['SegmentName'] ?? '')) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Display Order</label>
          <input class="form-control" type="number" name="DisplayOrder" value="<?= h((string) ($record['DisplayOrder'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Delimiter</label>
          <input class="form-control" type="text" maxlength="1" name="Delimiter" value="<?= h((string) ($record['Delimiter'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Min Length</label>
          <input class="form-control" type="number" name="MinLength" value="<?= h((string) ($record['MinLength'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Max Length</label>
          <input class="form-control" type="number" name="MaxLength" value="<?= h((string) ($record['MaxLength'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Start Point</label>
          <input class="form-control" type="number" name="StartPoint" value="<?= h((string) ($record['StartPoint'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">End Point</label>
          <input class="form-control" type="number" name="EndPoint" value="<?= h((string) ($record['EndPoint'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Parent Default</label>
          <input class="form-control" type="number" min="1" name="ParentSegmentNoDefault" value="<?= h((string) ($record['ParentSegmentNoDefault'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Type</label>
          <input class="form-control" type="text" maxlength="1" name="Type" value="<?= h((string) ($record['Type'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Editable</label>
          <input class="form-control" type="text" maxlength="1" name="Editable" value="<?= h((string) ($record['Editable'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Static</label>
          <input class="form-control" type="text" maxlength="1" name="Static" value="<?= h((string) ($record['Static'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Default Business Area</label>
          <input class="form-control" type="text" maxlength="1" name="DefaultBusinessArea" value="<?= h((string) ($record['DefaultBusinessArea'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">CBMS Dimension</label>
          <input id="segmentDimension" class="form-control" list="segmentDimensions" name="CBMSDimension" value="<?= h((string) ($record['CBMSDimension'] ?? '')) ?>">
          <datalist id="segmentDimensions">
            <?php foreach ($dimensions as $dimension): ?>
              <option value="<?= h($dimension) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="mb-3">
          <label class="form-label">Segment Group</label>
          <input id="segmentGroup" class="form-control" list="segmentGroups" name="SegmentGroup" value="<?= h((string) ($record['SegmentGroup'] ?? '')) ?>">
          <datalist id="segmentGroups">
            <?php foreach ($groups as $group): ?>
              <option value="<?= h($group) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="segmentUsedInFinancialAccount" name="UsedInFinancialAccount" <?= ((int) ($record['UsedInFinancialAccount'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="segmentUsedInFinancialAccount">Used In Financial Account</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="segmentUsedInStrategicPlanning" name="UsedInStrategicPlanning" <?= ((int) ($record['UsedInStrategicPlanning'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="segmentUsedInStrategicPlanning">Used In Strategic Planning</label>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Attribute 1 Name</label>
          <input class="form-control" type="text" name="Attribute1Name" value="<?= h((string) ($record['Attribute1Name'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Attribute 2 Name</label>
          <input class="form-control" type="text" name="Attribute2Name" value="<?= h((string) ($record['Attribute2Name'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Attribute 3 Name</label>
          <input class="form-control" type="text" name="Attribute3Name" value="<?= h((string) ($record['Attribute3Name'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Attribute 4 Name</label>
          <input class="form-control" type="text" name="Attribute4Name" value="<?= h((string) ($record['Attribute4Name'] ?? '')) ?>">
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" id="segmentParentRequired" name="ParentRequired" <?= ((int) ($record['ParentRequired'] ?? 0) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="segmentParentRequired">Parent Required</label>
        </div>

        <div class="d-flex justify-content-between">
          <a id="segments-back-btn" href="index.php?route=segments/list" class="btn btn-secondary">Back</a>
          <button id="segments-save-btn" type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
