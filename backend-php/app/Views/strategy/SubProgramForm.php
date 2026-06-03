<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['SubProgramID'] ?? 0);
$orgUnitCode = (string)($record['OrgUnitDataObjectCode'] ?? $sourceSubProgram['OrgUnitDataObjectCode'] ?? '');
$orgUnitName = (string)($record['OrgUnitName'] ?? $sourceSubProgram['OrgUnitName'] ?? '');
$sourceDataObjectCode = (string)($record['SourceDataObjectCode'] ?? $sourceSubProgram['SourceDataObjectCode'] ?? $orgUnitCode);
$sourceDataObjectName = (string)($record['SourceDataObjectName'] ?? $sourceSubProgram['SourceDataObjectName'] ?? ($sourceDataObjectCode === '0' ? 'Global' : $orgUnitName));
$subProgramCode = (string)($record['SubProgramCode'] ?? $sourceSubProgram['SubProgramCode'] ?? '');
$subProgramName = (string)($record['SubProgramName'] ?? $sourceSubProgram['SubProgramName'] ?? '');
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit SubProgram Overlay' : 'Configure SubProgram Overlay' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-setup/save-sub-program">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="SubProgramID" value="<?= $id ?>"><input type="hidden" name="OrgUnitDataObjectCode" value="<?= h($orgUnitCode) ?>"><input type="hidden" name="SourceDataObjectCode" value="<?= h($sourceDataObjectCode) ?>"><input type="hidden" name="SourceSegmentNo" value="<?= h((string)($record['SourceSegmentNo'] ?? $sourceSubProgram['SourceSegmentNo'] ?? '')) ?>"><input type="hidden" name="SubProgramCode" value="<?= h($subProgramCode) ?>"><input type="hidden" name="SubProgramName" value="<?= h($subProgramName) ?>">
<div class="alert alert-info">SubProgram code and name come from the configured segment values. This screen captures the Strategy record details only.</div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label">SubProgram Name</label><input type="text" class="form-control" readonly value="<?= h($subProgramName) ?>"></div>
<div class="col-md-6"><label class="form-label">Program Overlay</label><select name="ProgramID" class="form-select" required><option value="">Select configured program</option><?php foreach (($programOptions ?? []) as $option): ?><option value="<?= (int)$option['ProgramID'] ?>" <?= ((int)($record['ProgramID'] ?? 0) === (int)$option['ProgramID']) ? 'selected' : '' ?>><?= h((string)$option['ProgramName']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">SubProgram Code</label><input type="text" class="form-control" readonly value="<?= h($subProgramCode) ?>"></div>
<div class="col-md-8"><label class="form-label">Owner DataScope Org Unit</label><input type="text" class="form-control" readonly value="<?= h($orgUnitCode . ' / ' . $orgUnitName) ?>"></div>
<div class="col-12"><label class="form-label">Source Scope</label><input type="text" class="form-control" readonly value="<?= h($sourceDataObjectCode . ' / ' . $sourceDataObjectName) ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="SubProgramDescription" class="form-control" rows="3"><?= h((string)($record['SubProgramDescription'] ?? '')) ?></textarea></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="subProgramActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="subProgramActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-setup/sub-programs" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save Overlay</button></div>
</form></div></div></div>
