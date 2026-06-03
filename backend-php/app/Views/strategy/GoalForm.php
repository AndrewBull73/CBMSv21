<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['GoalID'] ?? 0);
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Goal' : 'Create Goal' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-performance/save-goal"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="GoalID" value="<?= $id ?>">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Code</label><input type="text" name="GoalCode" class="form-control" value="<?= h((string)($record['GoalCode'] ?? '')) ?>" required></div>
<div class="col-md-4"><label class="form-label">Type</label><select name="GoalTypeCode" class="form-select" required><?php $goalType = (string)($record['GoalTypeCode'] ?? 'SDG'); foreach (['SDG','NDP','NSDP','GOVT_PRIORITY','OTHER'] as $type): ?><option value="<?= h($type) ?>" <?= $goalType === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Strategic Pillar</label><select name="StrategicPillarID" class="form-select"><option value="">None</option><?php foreach (($strategicPillarOptions ?? []) as $option): ?><option value="<?= (int)$option['StrategicPillarID'] ?>" <?= ((int)($record['StrategicPillarID'] ?? 0) === (int)$option['StrategicPillarID']) ? 'selected' : '' ?>><?= h((string)$option['StrategicPillarCode'] . ' - ' . $option['StrategicPillarName']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Name</label><input type="text" name="GoalName" class="form-control" value="<?= h((string)($record['GoalName'] ?? '')) ?>" required></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="GoalDescription" class="form-control" rows="4"><?= h((string)($record['GoalDescription'] ?? '')) ?></textarea></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="goalActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="goalActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-performance/goals" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
