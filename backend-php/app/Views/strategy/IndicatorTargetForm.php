<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['IndicatorTargetID'] ?? 0);
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Indicator Target' : 'Create Indicator Target' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="alert alert-light border mb-4">Target context: <strong><?= h((string)($contextLabels['YearLabel'] ?? '')) ?></strong> / <strong><?= h((string)($contextLabels['VersionLabel'] ?? '')) ?></strong></div>
<form method="post" action="index.php?route=strategy-performance/save-target"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="IndicatorTargetID" value="<?= $id ?>">
<div class="row g-3">
<div class="col-md-8"><label class="form-label">Indicator</label><select name="IndicatorID" class="form-select" required><option value="">Select indicator</option><?php foreach (($indicatorOptions ?? []) as $option): ?><option value="<?= (int)$option['IndicatorID'] ?>" <?= ((int)($record['IndicatorID'] ?? 0) === (int)$option['IndicatorID']) ? 'selected' : '' ?>><?= h((string)$option['IndicatorTypeCode'] . ' / ' . $option['IndicatorName']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="targetActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="targetActiveFlag">Active</label></div></div>
<div class="col-md-6"><label class="form-label">Baseline Value</label><input type="number" step="0.000001" name="BaselineValue" class="form-control" value="<?= h((string)($record['BaselineValue'] ?? '')) ?>"></div>
<div class="col-md-6"><label class="form-label">Target Value</label><input type="number" step="0.000001" name="TargetValue" class="form-control" required value="<?= h((string)($record['TargetValue'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Notes</label><textarea name="Notes" class="form-control" rows="4"><?= h((string)($record['Notes'] ?? '')) ?></textarea></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-performance/targets" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
