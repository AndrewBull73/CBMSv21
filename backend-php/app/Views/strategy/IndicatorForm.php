<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['IndicatorID'] ?? 0);
$types = ['OUTCOME', 'OUTPUT'];
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Indicator' : 'Create Indicator' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-performance/save-indicator"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="IndicatorID" value="<?= $id ?>">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Indicator Type</label><select name="IndicatorTypeCode" class="form-select" required><?php foreach ($types as $type): ?><option value="<?= $type ?>" <?= (($record['IndicatorTypeCode'] ?? 'OUTCOME') === $type) ? 'selected' : '' ?>><?= $type ?></option><?php endforeach; ?></select></div>
<div class="col-md-8"><label class="form-label">Indicator Name</label><input type="text" name="IndicatorName" class="form-control" required value="<?= h((string)($record['IndicatorName'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Definition</label><textarea name="IndicatorDefinition" class="form-control" rows="3"><?= h((string)($record['IndicatorDefinition'] ?? '')) ?></textarea></div>
<div class="col-md-3"><label class="form-label">Unit Of Measure</label><input type="text" name="UnitOfMeasure" class="form-control" value="<?= h((string)($record['UnitOfMeasure'] ?? '')) ?>"></div>
<div class="col-md-3"><label class="form-label">Frequency</label><input type="text" name="FrequencyCode" class="form-control" value="<?= h((string)($record['FrequencyCode'] ?? '')) ?>"></div>
<div class="col-md-6"><label class="form-label">Data Source</label><input type="text" name="DataSource" class="form-control" value="<?= h((string)($record['DataSource'] ?? '')) ?>"></div>
<div class="col-md-6"><label class="form-label">Disaggregation</label><input type="text" name="Disaggregation" class="form-control" value="<?= h((string)($record['Disaggregation'] ?? '')) ?>"></div>
<div class="col-md-6"><label class="form-label">Quality Notes</label><textarea name="QualityNotes" class="form-control" rows="3"><?= h((string)($record['QualityNotes'] ?? '')) ?></textarea></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="indicatorActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="indicatorActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-performance/indicators" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
