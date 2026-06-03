<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['FundingSourceID'] ?? 0);
$types = $fundingTypeOptions ?? [];
$sourceSegmentCode = (string)($record['SourceSegmentCode'] ?? $sourceFundingSource['SourceSegmentCode'] ?? '');
$fundingSourceName = (string)($record['FundingSourceName'] ?? $sourceFundingSource['FundingSourceName'] ?? '');
$selectedFundingTypeCode = (string)($record['FundingTypeCode'] ?? 'DOMESTIC');
if ($types === []) {
    $types = [
        ['FundingTypeCode' => 'DOMESTIC', 'FundingTypeName' => 'Domestic'],
        ['FundingTypeCode' => 'GRANT', 'FundingTypeName' => 'Grant'],
        ['FundingTypeCode' => 'LOAN', 'FundingTypeName' => 'Loan'],
    ];
}
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Funding Source Overlay' : 'Configure Funding Source Overlay' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-setup/save-funding-source">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="FundingSourceID" value="<?= $id ?>"><input type="hidden" name="SourceSegmentCode" value="<?= h($sourceSegmentCode) ?>"><input type="hidden" name="FundingSourceName" value="<?= h($fundingSourceName) ?>">
<div class="alert alert-info">Funding source name comes from the configured segment values. This screen captures the Strategy record details only.</div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Funding Source Name</label><input type="text" class="form-control" readonly value="<?= h($fundingSourceName) ?>"></div>
<div class="col-md-3"><label class="form-label">Funding Type</label><select name="FundingTypeCode" class="form-select" required><?php foreach ($types as $type): ?><option value="<?= h((string)$type['FundingTypeCode']) ?>" <?= ($selectedFundingTypeCode === (string)$type['FundingTypeCode']) ? 'selected' : '' ?>><?= h((string)$type['FundingTypeName'] . ' (' . (string)$type['FundingTypeCode'] . ')') ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label">Source Code</label><input type="text" class="form-control" readonly value="<?= h($sourceSegmentCode) ?>"></div>
<div class="col-md-6"><label class="form-label">Donor</label><input type="text" name="DonorName" class="form-control" value="<?= h((string)($record['DonorName'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Conditions</label><textarea name="ConditionsText" class="form-control" rows="4"><?= h((string)($record['ConditionsText'] ?? '')) ?></textarea></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="fundingSourceActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="fundingSourceActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-setup/funding-sources" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save Overlay</button></div>
</form></div></div></div>
