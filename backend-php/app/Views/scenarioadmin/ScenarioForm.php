<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array $calcModel */
/** @var array|null $scenario */
/** @var array $parentOptions */
/** @var array $scenarioTypeOptions */
/** @var array $scenarioStatusOptions */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($scenario['ScenarioID'] ?? 0);
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-layers me-2"></i><?= $id > 0 ? 'Edit Scenario' : 'Create Scenario' ?></strong>
      <div class="small text-muted"><?= h((string) $calcModel['ModelCode']) ?> - <?= h((string) $calcModel['ModelName']) ?></div>
    </div>
    <a href="index.php?route=scenario-admin/detail&id=<?= (int) $calcModel['CalcModelID'] ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>

  <div class="card-body">
    <form method="post" action="index.php?route=scenario-admin/saveScenario">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="CalcModelID" value="<?= (int) $calcModel['CalcModelID'] ?>">
      <?php if ($id > 0): ?>
        <input type="hidden" name="ScenarioID" value="<?= $id ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Scenario Code</label>
          <input type="text" name="ScenarioCode" class="form-control" required value="<?= h((string) ($scenario['ScenarioCode'] ?? '')) ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Scenario Name</label>
          <input type="text" name="ScenarioName" class="form-control" required value="<?= h((string) ($scenario['ScenarioName'] ?? '')) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Scenario Type</label>
          <select name="ScenarioTypeCode" class="form-select">
            <?php foreach ($scenarioTypeOptions as $option): ?>
              <option value="<?= h($option) ?>" <?= (($scenario['ScenarioTypeCode'] ?? 'BASE') === $option) ? 'selected' : '' ?>><?= h($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="ScenarioStatusCode" class="form-select">
            <?php foreach ($scenarioStatusOptions as $option): ?>
              <option value="<?= h($option) ?>" <?= (($scenario['ScenarioStatusCode'] ?? 'DRAFT') === $option) ? 'selected' : '' ?>><?= h($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Parent Scenario</label>
          <select name="ParentScenarioID" class="form-select">
            <option value="">None</option>
            <?php foreach ($parentOptions as $option): ?>
              <option value="<?= (int) $option['ScenarioID'] ?>" <?= ((string) ($scenario['ParentScenarioID'] ?? '') === (string) $option['ScenarioID']) ? 'selected' : '' ?>>
                <?= h((string) $option['ScenarioCode']) ?> - <?= h((string) $option['ScenarioName']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort Order</label>
          <input type="number" min="1" name="SortOrder" class="form-control" value="<?= (int) ($scenario['SortOrder'] ?? 100) ?>">
        </div>

        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check me-3">
            <input type="checkbox" class="form-check-input" id="LockedFlag" name="LockedFlag" value="1" <?= ((int) ($scenario['LockedFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="LockedFlag">Locked</label>
          </div>
          <div class="form-check me-3">
            <input type="checkbox" class="form-check-input" id="ApprovedFlag" name="ApprovedFlag" value="1" <?= ((int) ($scenario['ApprovedFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ApprovedFlag">Approved</label>
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="ActiveFlag" name="ActiveFlag" value="1" <?= ((int) ($scenario['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ActiveFlag">Active</label>
          </div>
        </div>
      </div>

      <hr class="my-4">
      <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted">Use parent inheritance when you want a what-if scenario to override only selected values.</div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save Scenario
        </button>
      </div>
    </form>
  </div>
</div>
