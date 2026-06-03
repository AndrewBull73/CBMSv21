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
$stageTypeOptions = is_array($stageTypeOptions ?? null) ? $stageTypeOptions : [];
$workflowAreaCode = strtoupper(trim((string) ($workflowAreaCode ?? ($record['WorkflowAreaCode'] ?? ''))));
$tableInstalled = !empty($tableInstalled);
$ctx = is_array($_ctx ?? null) ? $_ctx : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ($ctx['FiscalYearID'] ?? '')));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ($ctx['VersionID'] ?? '')));
$screenHeader = [
    'titleKey' => !empty($record) ? 'workflow_engine_edit_stage_title' : 'workflow_engine_create_stage_title',
    'title' => !empty($record) ? 'Edit Workflow Stage' : 'Create Workflow Stage',
    'icon' => 'bi-layers',
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
          <strong><?= h(__t('workflow_engine_tables_missing_title')) ?></strong> <?= h(__t('workflow_engine_tables_missing_stage_help')) ?> <code>create_workflow_engine_foundation_v1.sql</code>.
        </div>
      <?php else: ?>
        <div class="alert alert-info border-0 shadow-sm mb-4">
          <?= h(__t('workflow_engine_stage_intro')) ?>
        </div>

        <form method="post" action="index.php?route=workflow-engine/save-stage" id="workflow-engine-stage-form">
          <?= csrf_field() ?>
          <input type="hidden" name="WorkflowDefinitionStageID" value="<?= (int) ($record['WorkflowDefinitionStageID'] ?? 0) ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="WorkflowStageAreaCode"><?= h(__t('workflow_area')) ?></label>
              <select class="form-select" name="WorkflowAreaCode" id="WorkflowStageAreaCode" required>
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
              <label class="form-label" for="WorkflowStageOrder"><?= h(__t('stage_order')) ?></label>
              <input class="form-control" type="number" min="1" name="StageOrder" id="WorkflowStageOrder" value="<?= h((string) ($record['StageOrder'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowStageCode"><?= h(__t('stage_code')) ?></label>
              <input class="form-control" type="text" name="WorkflowStageCode" id="WorkflowStageCode" value="<?= h((string) ($record['WorkflowStageCode'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowStageName"><?= h(__t('stage_name')) ?></label>
              <input class="form-control" type="text" name="WorkflowStageName" id="WorkflowStageName" value="<?= h((string) ($record['WorkflowStageName'] ?? '')) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowStageType"><?= h(__t('stage_type')) ?></label>
              <select class="form-select" name="StageType" id="WorkflowStageType" required>
                <?php foreach ($stageTypeOptions as $option): ?>
                  <?php $optionCode = strtoupper(trim((string) ($option['code'] ?? ''))); ?>
                  <option value="<?= h($optionCode) ?>" <?= (strtoupper(trim((string) ($record['StageType'] ?? 'OTHER'))) === $optionCode) ? 'selected' : '' ?>>
                    <?= h((string) ($option['label'] ?? $optionCode)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="WorkflowStagePermissions"><?= h(__t('required_permission_codes')) ?></label>
              <input class="form-control" type="text" name="RequiredPermissionCodes" id="WorkflowStagePermissions" value="<?= h((string) ($record['RequiredPermissionCodes'] ?? '')) ?>" placeholder="<?= h(__t('comma_separated_permission_codes')) ?>">
            </div>
          </div>

          <div class="row g-3 mt-1 mb-4">
            <?php
              $checkboxes = [
                  'RouteByDataObjectHierarchy' => 'Route By DataObjectHierarchy',
                  'AllowReturn' => 'Allow Return',
                  'AllowReject' => 'Allow Reject',
                  'AllowCancel' => 'Allow Cancel',
                  'AllowsDelegation' => 'Allows Delegation',
                  'RequireDifferentActorFromPreviousStage' => 'Require Different Actor From Previous Stage',
                  'IsDraftStage' => 'Is Draft Stage',
                  'IsFinalStage' => 'Is Final Stage',
                  'ActiveFlag' => 'Active',
              ];
            ?>
            <?php foreach ($checkboxes as $field => $label): ?>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="<?= h($field) ?>" name="<?= h($field) ?>" <?= ((int) ($record[$field] ?? ($field === 'RouteByDataObjectHierarchy' || $field === 'ActiveFlag' ? 1 : 0)) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="<?= h($field) ?>"><?= h($label) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex justify-content-between">
            <a href="index.php?route=workflow-engine/form<?= $workflowAreaCode !== '' ? '&workflow_area_code=' . urlencode($workflowAreaCode) : '' ?>" id="workflow-engine-stage-back-btn" class="btn btn-sm btn-outline-secondary"><?= h(__t('back')) ?></a>
            <button type="submit" id="workflow-engine-stage-save-btn" class="btn btn-sm btn-primary"><?= h(__t('save_stage')) ?></button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
