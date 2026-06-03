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
$id = (int) ($record['NarrativeID'] ?? 0);
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$supportsNarrativeProjectLink = (bool) ($supportsNarrativeProjectLink ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i><?= $id > 0 ? 'Edit BSP Narrative' : 'Create BSP Narrative' ?></h3>
      <a href="index.php?route=strategy-governance/narratives" class="btn btn-sm btn-outline-secondary">Back to Narratives</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Context: <strong><?= h($yearLabel) ?></strong><?php if ($versionLabel !== ''): ?> / <strong><?= h($versionLabel) ?></strong><?php endif; ?></div>

      <form method="post" action="index.php?route=strategy-governance/save-narrative" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="NarrativeID" value="<?= $id ?>">
        <div class="col-md-4">
          <label class="form-label">Section</label>
          <select name="SectionCode" class="form-select form-select-sm" required>
            <option value="">Select section</option>
            <?php foreach (($sectionOptions ?? []) as $code => $label): ?>
              <option value="<?= h((string) $code) ?>" <?= ((string) ($record['SectionCode'] ?? '') === (string) $code) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Org Unit Scope</label>
          <select name="OrgUnitID" class="form-select form-select-sm">
            <option value="">None</option>
            <?php foreach (($orgUnitOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['OrgUnitID'] ?>" <?= ((int) ($record['OrgUnitID'] ?? 0) === (int) $option['OrgUnitID']) ? 'selected' : '' ?>><?= h((string) $option['OrgUnitName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Sector Scope</label>
          <select name="SectorID" class="form-select form-select-sm">
            <option value="">None</option>
            <?php foreach (($sectorOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['SectorID'] ?>" <?= ((int) ($record['SectorID'] ?? 0) === (int) $option['SectorID']) ? 'selected' : '' ?>><?= h((string) $option['SectorName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Program Scope</label>
          <select name="ProgramID" class="form-select form-select-sm">
            <option value="">None</option>
            <?php foreach (($programOptions ?? []) as $option): ?>
              <option value="<?= (int) $option['ProgramID'] ?>" <?= ((int) ($record['ProgramID'] ?? 0) === (int) $option['ProgramID']) ? 'selected' : '' ?>><?= h((string) (($option['ProgramCode'] ?? '') !== '' ? $option['ProgramCode'] . ' / ' : '') . $option['ProgramName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Project Scope</label>
          <?php if ($supportsNarrativeProjectLink): ?>
            <select name="ProjectID" class="form-select form-select-sm">
              <option value="">None</option>
              <?php foreach (($projectOptions ?? []) as $option): ?>
                <option value="<?= (int) $option['ProjectID'] ?>" <?= ((int) ($record['ProjectID'] ?? 0) === (int) $option['ProjectID']) ? 'selected' : '' ?>>
                  <?= h((string) (($option['ProjectCode'] ?? '') !== '' ? $option['ProjectCode'] . ' / ' : '') . ($option['ProjectName'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <div class="alert alert-warning py-2 mb-0">Run <code>alter_tblSbNarrative_add_project.sql</code> to enable project scope.</div>
          <?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort Order</label>
          <input type="number" name="SortOrder" class="form-control form-control-sm" value="<?= h((string) ($record['SortOrder'] ?? 0)) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end gap-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="LockedFlag" id="LockedFlag" <?= ((int) ($record['LockedFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="LockedFlag">Locked</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" <?= !isset($record['ActiveFlag']) || (int) ($record['ActiveFlag'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="ActiveFlag">Active</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Title</label>
          <input type="text" name="NarrativeTitle" class="form-control form-control-sm" value="<?= h((string) ($record['NarrativeTitle'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Body Text</label>
          <textarea name="BodyText" class="form-control form-control-sm" rows="12" required><?= h((string) ($record['BodyText'] ?? '')) ?></textarea>
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary btn-sm" type="submit">Save Narrative</button>
          <a href="index.php?route=strategy-governance/narratives" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
