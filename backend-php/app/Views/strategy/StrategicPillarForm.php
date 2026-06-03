<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['StrategicPillarID'] ?? 0);
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Strategic Pillar' : 'Create Strategic Pillar' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-performance/save-strategic-pillar"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="StrategicPillarID" value="<?= $id ?>">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Code</label><input type="text" name="StrategicPillarCode" class="form-control" value="<?= h((string)($record['StrategicPillarCode'] ?? '')) ?>" required></div>
<div class="col-md-4"><label class="form-label">Framework</label><input type="text" name="FrameworkCode" class="form-control" value="<?= h((string)($record['FrameworkCode'] ?? 'FYDP_II')) ?>" required></div>
<div class="col-md-4"><label class="form-label">Status</label><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="strategicPillarActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="strategicPillarActiveFlag">Active</label></div></div>
<div class="col-12"><label class="form-label">Name</label><input type="text" name="StrategicPillarName" class="form-control" value="<?= h((string)($record['StrategicPillarName'] ?? '')) ?>" required></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="StrategicPillarDescription" class="form-control" rows="4"><?= h((string)($record['StrategicPillarDescription'] ?? '')) ?></textarea></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-performance/strategic-pillars" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
