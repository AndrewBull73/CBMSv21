<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['EconomicItemID'] ?? 0);
$economicCode = (string)($record['EconomicCode'] ?? $sourceEconomicItem['EconomicCode'] ?? '');
$economicName = (string)($record['EconomicName'] ?? $sourceEconomicItem['EconomicName'] ?? '');
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Economic Item Overlay' : 'Configure Economic Item Overlay' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-setup/save-economic-item">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="EconomicItemID" value="<?= $id ?>"><input type="hidden" name="EconomicCode" value="<?= h($economicCode) ?>"><input type="hidden" name="EconomicName" value="<?= h($economicName) ?>">
<div class="alert alert-info">Economic code and name come from the configured segment values. This screen captures the Strategy record details only.</div>
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Economic Code</label><input type="text" class="form-control" readonly value="<?= h($economicCode) ?>"></div>
<div class="col-md-5"><label class="form-label">Economic Name</label><input type="text" class="form-control" readonly value="<?= h($economicName) ?>"></div>
<div class="col-md-3"><label class="form-label">Level</label><input type="number" name="EconomicLevel" class="form-control" value="<?= h((string)($record['EconomicLevel'] ?? '')) ?>"></div>
<div class="col-md-6"><label class="form-label">Parent Economic Item</label><select name="ParentEconomicItemID" class="form-select"><option value="">None</option><?php foreach (($parentOptions ?? []) as $option): if ((int)$option['EconomicItemID'] === $id) continue; ?><option value="<?= (int)$option['EconomicItemID'] ?>" <?= ((int)($record['ParentEconomicItemID'] ?? 0) === (int)$option['EconomicItemID']) ? 'selected' : '' ?>><?= h((string)$option['EconomicCode'] . ' / ' . $option['EconomicName']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="economicItemActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="economicItemActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-setup/economic-items" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save Overlay</button></div>
</form></div></div></div>
