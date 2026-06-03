<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($record['ProgramID'] ?? 0);
$sourceMode = !empty($sourceProgram) || $id === 0;
$orgUnitCode = (string) ($record['OrgUnitDataObjectCode'] ?? $sourceProgram['OrgUnitDataObjectCode'] ?? '');
$orgUnitName = (string) ($record['OrgUnitName'] ?? $sourceProgram['OrgUnitName'] ?? '');
$sourceDataObjectCode = (string) ($record['SourceDataObjectCode'] ?? $sourceProgram['SourceDataObjectCode'] ?? $orgUnitCode);
$sourceDataObjectName = (string) ($record['SourceDataObjectName'] ?? $sourceProgram['SourceDataObjectName'] ?? ($sourceDataObjectCode === '0' ? 'Global' : $orgUnitName));
$programCode = (string) ($record['ProgramCode'] ?? $sourceProgram['ProgramCode'] ?? '');
$programName = (string) ($record['ProgramName'] ?? $sourceProgram['ProgramName'] ?? '');
$selectedOwnerDataObjectCode = (string) ($record['OrgUnitDataObjectCode'] ?? $orgUnitCode);
$selectedLinkedDataObjectCodes = $selectedLinkedDataObjectCodes ?? [];
$linkedOrgUnits = $linkedOrgUnits ?? [];
$supportsProgramOrgLinks = (bool) ($supportsProgramOrgLinks ?? false);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Program Overlay' : 'Configure Program Overlay' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-setup/save-program" id="program-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="ProgramID" value="<?= $id ?>">
        <input type="hidden" name="OrgUnitDataObjectCode" value="<?= h($orgUnitCode) ?>">
        <input type="hidden" name="SourceDataObjectCode" value="<?= h($sourceDataObjectCode) ?>">
        <input type="hidden" name="SourceSegmentNo" value="<?= h((string) ($record['SourceSegmentNo'] ?? $sourceProgram['SourceSegmentNo'] ?? '')) ?>">
        <input type="hidden" name="ProgramCode" value="<?= h($programCode) ?>">
        <input type="hidden" name="ProgramName" value="<?= h($programName) ?>">

        <?php if ($sourceMode): ?>
          <div class="alert alert-info">
            Program code and name come from the configured segment values. This screen captures the Strategy record details only.
          </div>
        <?php endif; ?>

        <div class="alert alert-secondary">
          Use one primary sector and one primary owner. If the program is cross-cutting, keep the main owner above and add other participating DataScope org units under additional linked org units.
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="ProgramNameDisplay">Program Name</label>
            <input type="text" id="ProgramNameDisplay" class="form-control" readonly value="<?= h($programName) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="ProgramCodeDisplay">Program Code</label>
            <input type="text" id="ProgramCodeDisplay" class="form-control" readonly value="<?= h($programCode) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="ProgramManagerName">Manager</label>
            <input type="text" name="ProgramManagerName" id="ProgramManagerName" class="form-control" value="<?= h((string) ($record['ProgramManagerName'] ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="<?= $sourceDataObjectCode === '0' ? 'OwnerDataObjectCode' : 'OwnerDataObjectCodeDisplay' ?>"><?= $sourceDataObjectCode === '0' ? 'Primary Owner DataScope Org Unit' : 'Owner DataScope Org Unit' ?></label>
            <?php if ($sourceDataObjectCode === '0'): ?>
              <select name="OwnerDataObjectCode" id="OwnerDataObjectCode" class="form-select" required>
                <option value="">Select owner org unit</option>
                <?php foreach (($orgUnitOptions ?? []) as $option): ?>
                  <?php $optionCode = (string) ($option['DataObjectCode'] ?? ''); ?>
                  <option value="<?= h($optionCode) ?>" <?= $selectedOwnerDataObjectCode === $optionCode ? 'selected' : '' ?>>
                    <?= h($optionCode . ' / ' . (string) ($option['DataObjectName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">This program source is global, so choose the primary accountable owner for the Strategy record.</div>
            <?php else: ?>
              <input type="text" id="OwnerDataObjectCodeDisplay" class="form-control" readonly value="<?= h($orgUnitCode . ' / ' . $orgUnitName) ?>">
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="SectorID">Sector</label>
            <select name="SectorID" id="SectorID" class="form-select" required>
              <option value="">Select sector</option>
              <?php foreach (($sectorOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['SectorID'] ?>" <?= ((int) ($record['SectorID'] ?? 0) === (int) $option['SectorID']) ? 'selected' : '' ?>>
                  <?= h((string) $option['SectorName']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Source Scope</label>
            <input type="text" class="form-control" readonly value="<?= h($sourceDataObjectCode . ' / ' . $sourceDataObjectName) ?>">
          </div>
          <div class="col-12">
            <label class="form-label" for="LinkedDataObjectCodes">Additional Linked DataScope Org Units</label>
            <?php if ($supportsProgramOrgLinks): ?>
              <select name="LinkedDataObjectCodes[]" id="LinkedDataObjectCodes" class="form-select" multiple size="8">
                <?php foreach (($orgUnitOptions ?? []) as $option): ?>
                  <?php
                    $optionCode = (string) ($option['DataObjectCode'] ?? '');
                    $optionName = (string) ($option['DataObjectName'] ?? '');
                    if ($optionCode === '' || $optionCode === $selectedOwnerDataObjectCode) {
                        continue;
                    }
                  ?>
                  <option value="<?= h($optionCode) ?>" <?= in_array($optionCode, $selectedLinkedDataObjectCodes, true) ? 'selected' : '' ?>>
                    <?= h($optionCode . ' - ' . $optionName) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Use this for cross-cutting programs that involve additional DataScope org units. The owner org unit above remains the primary accountable owner.</div>
              <?php if (!empty($linkedOrgUnits)): ?>
                <div class="mt-2">
                  <?php foreach ($linkedOrgUnits as $link): ?>
                    <span class="badge text-bg-light border me-1 mb-1"><?= h((string) ($link['DataObjectCode'] ?? '')) ?><?= !empty($link['DataObjectName']) ? ' - ' . h((string) $link['DataObjectName']) : '' ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <div class="alert alert-warning mb-0">
                Additional program org links are not available yet in this database. Run <code>create_tblSbProgramOrgLink.sql</code> to enable cross-DataScope program ownership links.
              </div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="ProgramDescription">Description</label>
            <textarea name="ProgramDescription" id="ProgramDescription" class="form-control" rows="4"><?= h((string) ($record['ProgramDescription'] ?? '')) ?></textarea>
          </div>
          <?php require __DIR__ . '/_CustomAttributes.php'; ?>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="programActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="programActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-setup/programs" id="programs-back-btn" class="btn btn-secondary">Back</a>
          <button type="submit" id="program-save-btn" class="btn btn-primary">Save Overlay</button>
        </div>
      </form>
    </div>
  </div>
</div>
