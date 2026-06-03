<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$workflowAreas = is_array($workflowAreas ?? null) ? $workflowAreas : [];
$workflowStages = is_array($workflowStages ?? null) ? $workflowStages : [];
$actionTypeOptions = is_array($actionTypeOptions ?? null) ? $actionTypeOptions : [];
$workflowAreaCode = strtoupper(trim((string) ($workflowAreaCode ?? ($record['WorkflowAreaCode'] ?? ''))));
$tableInstalled = !empty($tableInstalled);
$ctx = is_array($_ctx ?? null) ? $_ctx : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ($ctx['FiscalYearID'] ?? '')));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ($ctx['VersionID'] ?? '')));
$screenHeader = [
    'titleKey' => !empty($record) ? 'workflow_engine_edit_action_title' : 'workflow_engine_create_action_title',
    'title' => !empty($record) ? 'Edit Workflow Action' : 'Create Workflow Action',
    'icon' => 'bi-arrow-left-right',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        <?= h(__t('current_context')) ?>:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-0">
          <strong><?= h(__t('workflow_engine_tables_missing_title')) ?></strong> <?= h(__t('workflow_engine_tables_missing_action_help')) ?> <code>create_workflow_engine_foundation_v1.sql</code>.
        </div>
      <?php else: ?>
        <div class="alert alert-info border-0 shadow-sm mb-4">
          <?= h(__t('workflow_engine_action_intro')) ?>
        </div>

        <form method="post" action="index.php?route=workflow-engine/save-action" id="workflow-engine-action-form">
          <?= csrf_field() ?>
          <input type="hidden" name="WorkflowDefinitionActionID" value="<?= (int) ($record['WorkflowDefinitionActionID'] ?? 0) ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="WorkflowActionAreaCode"><?= h(__t('workflow_area')) ?></label>
              <select class="form-select" name="WorkflowAreaCode" id="WorkflowActionAreaCode" required>
                <option value=""><?= h(__t('select_workflow_area')) ?></option>
                <?php foreach ($workflowAreas as $workflowArea): ?>
                  <?php $areaCode = strtoupper(trim((string) ($workflowArea['WorkflowAreaCode'] ?? ''))); ?>
                  <option value="<?= h($areaCode) ?>" <?= ($workflowAreaCode === $areaCode) ? 'selected' : '' ?>>
                    <?= h((string) ($workflowArea['WorkflowAreaName'] ?? $areaCode)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowActionType"><?= h(__t('action_type')) ?></label>
              <select class="form-select" name="ActionType" id="WorkflowActionType" required>
                <?php foreach ($actionTypeOptions as $option): ?>
                  <?php $optionCode = strtoupper(trim((string) ($option['code'] ?? ''))); ?>
                  <option value="<?= h($optionCode) ?>" <?= (strtoupper(trim((string) ($record['ActionType'] ?? 'OTHER'))) === $optionCode) ? 'selected' : '' ?>>
                    <?= h((string) ($option['label'] ?? $optionCode)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowActionCode"><?= h(__t('action_code')) ?></label>
              <input class="form-control" type="text" name="WorkflowActionCode" id="WorkflowActionCode" value="<?= h((string) ($record['WorkflowActionCode'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowActionName"><?= h(__t('action_name')) ?></label>
              <input class="form-control" type="text" name="WorkflowActionName" id="WorkflowActionName" value="<?= h((string) ($record['WorkflowActionName'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowActionFromStage"><?= h(__t('from_stage')) ?></label>
              <select class="form-select" name="FromStageCode" id="WorkflowActionFromStage" required>
                <option value=""><?= h(__t('select_from_stage')) ?></option>
                <?php foreach ($workflowStages as $stage): ?>
                  <?php $stageCode = strtoupper(trim((string) ($stage['WorkflowStageCode'] ?? ''))); ?>
                  <option value="<?= h($stageCode) ?>" <?= (strtoupper(trim((string) ($record['FromStageCode'] ?? ''))) === $stageCode) ? 'selected' : '' ?>>
                    <?= h((string) ($stage['WorkflowAreaCode'] ?? '')) ?> / <?= h((string) ($stage['WorkflowStageName'] ?? $stageCode)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowActionToStage"><?= h(__t('to_stage')) ?></label>
              <select class="form-select" name="ToStageCode" id="WorkflowActionToStage" required>
                <option value=""><?= h(__t('select_to_stage')) ?></option>
                <?php foreach ($workflowStages as $stage): ?>
                  <?php $stageCode = strtoupper(trim((string) ($stage['WorkflowStageCode'] ?? ''))); ?>
                  <option value="<?= h($stageCode) ?>" <?= (strtoupper(trim((string) ($record['ToStageCode'] ?? ''))) === $stageCode) ? 'selected' : '' ?>>
                    <?= h((string) ($stage['WorkflowAreaCode'] ?? '')) ?> / <?= h((string) ($stage['WorkflowStageName'] ?? $stageCode)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="WorkflowActionPermissions"><?= h(__t('required_permission_codes')) ?></label>
              <input class="form-control" type="text" name="RequiredPermissionCodes" id="WorkflowActionPermissions" value="<?= h((string) ($record['RequiredPermissionCodes'] ?? '')) ?>" placeholder="<?= h(__t('comma_separated_permission_codes')) ?>">
            </div>
          </div>

          <div class="row g-3 mt-1 mb-4">
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="workflowActionRequireNote" name="RequireNote" <?= ((int) ($record['RequireNote'] ?? 0) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="workflowActionRequireNote"><?= h(__t('require_note')) ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="workflowActionActive" name="ActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="workflowActionActive"><?= h(__t('active')) ?></label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <a href="index.php?route=workflow-engine/form<?= $workflowAreaCode !== '' ? '&workflow_area_code=' . urlencode($workflowAreaCode) : '' ?>" id="workflow-engine-action-back-btn" class="btn btn-sm btn-outline-secondary"><?= h(__t('back')) ?></a>
            <button type="submit" id="workflow-engine-action-save-btn" class="btn btn-sm btn-primary"><?= h(__t('save_action')) ?></button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
