<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['FundingTypeID'] ?? 0);
$sourceSegmentCode = (string)($record['SourceSegmentCode'] ?? $sourceFundingType['SourceSegmentCode'] ?? '');
$fundingTypeCode = (string)($record['FundingTypeCode'] ?? $sourceSegmentCode);
$fundingTypeName = (string)($record['FundingTypeName'] ?? $sourceFundingType['FundingTypeName'] ?? '');
$phasingProfiles = is_array($phasingProfiles ?? null) ? $phasingProfiles : [];
$supportsFundingTypeDefaultPhasing = !empty($supportsFundingTypeDefaultPhasing);
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Funding Type Overlay' : 'Configure Funding Type Overlay' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-setup/save-funding-type">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="FundingTypeID" value="<?= $id ?>"><input type="hidden" name="SourceSegmentCode" value="<?= h($sourceSegmentCode) ?>">
<?php if ($sourceSegmentCode !== ''): ?><div class="alert alert-info">This funding type comes from the configured segment values. You can keep the source-linked code/name and maintain any extra description here.</div><?php else: ?><div class="alert alert-info">Use this screen to maintain funding types directly when the client does not map them from a CBMS segment.</div><?php endif; ?>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Funding Type Code</label><input type="text" name="FundingTypeCode" class="form-control" required value="<?= h($fundingTypeCode) ?>"></div>
<div class="col-md-8"><label class="form-label">Funding Type Name</label><input type="text" name="FundingTypeName" class="form-control" required value="<?= h($fundingTypeName) ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="FundingTypeDescription" class="form-control" rows="4"><?= h((string)($record['FundingTypeDescription'] ?? '')) ?></textarea></div>
<?php if ($supportsFundingTypeDefaultPhasing): ?><div class="col-md-6"><label class="form-label">Default Phasing Profile</label><select name="DefaultPhasingProfileID" class="form-select"><option value="">No default</option><?php foreach ($phasingProfiles as $profile): ?><option value="<?= (int)($profile['PhasingProfileID'] ?? 0) ?>" <?= ((int)($record['DefaultPhasingProfileID'] ?? 0) === (int)($profile['PhasingProfileID'] ?? 0)) ? 'selected' : '' ?>><?= h((string)($profile['ProfileName'] ?? '')) ?></option><?php endforeach; ?></select><div class="form-text">If set, new Resource Envelope lines for this funding type will preselect this phasing helper.</div></div><?php endif; ?>
<?php if ($sourceSegmentCode !== ''): ?><div class="col-md-4"><label class="form-label">Source Code</label><input type="text" class="form-control" readonly value="<?= h($sourceSegmentCode) ?>"></div><?php endif; ?>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="fundingTypeActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="fundingTypeActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-setup/funding-types" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save Overlay</button></div>
</form></div></div></div>
