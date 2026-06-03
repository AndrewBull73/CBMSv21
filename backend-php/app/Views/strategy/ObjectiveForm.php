<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../shared/csrf.php';
if (!function_exists('h')) { function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); } }
$id = (int)($record['ObjectiveID'] ?? 0);
$selectedGoalIds = array_map('intval', $selectedGoalIds ?? []);
?>
<div class="container mt-4"><div class="card shadow-sm"><div class="card-header"><h3 class="mb-0"><?= $id > 0 ? 'Edit Objective' : 'Create Objective' ?></h3></div><div class="card-body">
<?php if (!empty($flash)): ?><div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show"><?= h((string)($flash['text'] ?? '')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<form method="post" action="index.php?route=strategy-performance/save-objective"><input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="ObjectiveID" value="<?= $id ?>">
<div class="row g-3">
<div class="col-md-6"><label class="form-label">Program</label><select name="ProgramID" class="form-select" required><option value="">Select program</option><?php foreach (($programOptions ?? []) as $option): ?><option value="<?= (int)$option['ProgramID'] ?>" <?= ((int)($record['ProgramID'] ?? 0) === (int)$option['ProgramID']) ? 'selected' : '' ?>><?= h((string)$option['ProgramName']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">SubProgram</label><select name="SubProgramID" class="form-select"><option value="">None</option><?php foreach (($subProgramOptions ?? []) as $option): ?><option value="<?= (int)$option['SubProgramID'] ?>" <?= ((int)($record['SubProgramID'] ?? 0) === (int)$option['SubProgramID']) ? 'selected' : '' ?>><?= h((string)$option['ProgramName'] . ' / ' . $option['SubProgramName']) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Objective Text</label><textarea name="ObjectiveText" class="form-control" rows="4" required><?= h((string)($record['ObjectiveText'] ?? '')) ?></textarea></div>
<div class="col-md-8"><label class="form-label">Policy Link</label><input type="text" name="PolicyLink" class="form-control" value="<?= h((string)($record['PolicyLink'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label">Priority Rank</label><input type="number" name="PriorityRank" class="form-control" value="<?= h((string)($record['PriorityRank'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Goal</label><select name="GoalIDs[]" class="form-select" multiple size="6"><?php foreach (($goalOptions ?? []) as $option): ?><option value="<?= (int)$option['GoalID'] ?>" <?= in_array((int)$option['GoalID'], $selectedGoalIds, true) ? 'selected' : '' ?>><?= h((string)$option['GoalCode'] . ' - ' . $option['GoalName']) ?></option><?php endforeach; ?></select><div class="form-text">Select one or more higher-level goals such as SDGs that this objective supports.</div></div>
<?php require __DIR__ . '/_CustomAttributes.php'; ?>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ActiveFlag" id="objectiveActiveFlag" <?= ((int)($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>><label class="form-check-label" for="objectiveActiveFlag">Active</label></div></div>
</div>
<div class="d-flex justify-content-between mt-4"><a href="index.php?route=strategy-performance/objectives" class="btn btn-secondary">Back</a><button type="submit" class="btn btn-primary">Save</button></div>
</form></div></div></div>
