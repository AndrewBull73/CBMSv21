<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : null;
$stageRows = is_array($stageRows ?? null) ? $stageRows : [];
$actionRows = is_array($actionRows ?? null) ? $actionRows : [];
$tableInstalled = !empty($tableInstalled);
$workflowAreaCode = strtoupper(trim((string) ($record['WorkflowAreaCode'] ?? '')));
$ctx = is_array($_ctx ?? null) ? $_ctx : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ($ctx['FiscalYearID'] ?? '')));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ($ctx['VersionID'] ?? '')));
$draftStageCount = count(array_filter($stageRows, static fn(array $row): bool => (int) ($row['IsDraftStage'] ?? 0) === 1));
$finalStageCount = count(array_filter($stageRows, static fn(array $row): bool => (int) ($row['IsFinalStage'] ?? 0) === 1));
$screenHeader = [
    'titleKey' => $record !== null ? 'workflow_engine_edit_definition_title' : 'workflow_engine_create_definition_title',
    'title' => $record !== null ? 'Edit Workflow Definition' : 'Create Workflow Definition',
    'icon' => 'bi-diagram-3',
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
          <strong><?= h(__t('workflow_engine_tables_missing_title')) ?></strong> <?= h(__t('workflow_engine_tables_missing_help')) ?> <code>create_workflow_engine_foundation_v1.sql</code>.
        </div>
      <?php else: ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Stages</div>
                <div class="fs-4 fw-semibold"><?= h((string) count($stageRows)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Actions</div>
                <div class="fs-4 fw-semibold"><?= h((string) count($actionRows)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Draft Stages</div>
                <div class="fs-4 fw-semibold"><?= h((string) $draftStageCount) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Final Stages</div>
                <div class="fs-4 fw-semibold"><?= h((string) $finalStageCount) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="alert alert-info border-0 shadow-sm mb-4">
          <?= h(__t('workflow_engine_definition_intro')) ?>
        </div>

        <form method="post" action="index.php?route=workflow-engine/save" class="mb-4" id="workflow-engine-definition-form">
          <?= csrf_field() ?>
          <input type="hidden" name="OriginalWorkflowAreaCode" value="<?= h((string) ($record['WorkflowAreaCode'] ?? '')) ?>">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="WorkflowAreaCode"><?= h(__t('workflow_area_code')) ?></label>
              <input class="form-control" type="text" name="WorkflowAreaCode" id="WorkflowAreaCode" value="<?= h((string) ($record['WorkflowAreaCode'] ?? '')) ?>" required>
            </div>
            <div class="col-md-8">
              <label class="form-label" for="WorkflowAreaName"><?= h(__t('workflow_area_name')) ?></label>
              <input class="form-control" type="text" name="WorkflowAreaName" id="WorkflowAreaName" value="<?= h((string) ($record['WorkflowAreaName'] ?? '')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="WorkflowModuleCode"><?= h(__t('module_code')) ?></label>
              <input class="form-control" type="text" name="ModuleCode" id="WorkflowModuleCode" value="<?= h((string) ($record['ModuleCode'] ?? '')) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label" for="WorkflowRecordTableName"><?= h(__t('record_table_name')) ?></label>
              <input class="form-control" type="text" name="RecordTableName" id="WorkflowRecordTableName" value="<?= h((string) ($record['RecordTableName'] ?? '')) ?>" placeholder="<?= h(__t('workflow_engine_record_table_placeholder')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="WorkflowDescription"><?= h(__t('description')) ?></label>
              <textarea class="form-control" name="Description" id="WorkflowDescription" rows="3"><?= h((string) ($record['Description'] ?? '')) ?></textarea>
            </div>
          </div>

          <div class="row g-3 mt-1 mb-4">
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="workflowRouteByHierarchy" name="RouteByDataObjectHierarchy" <?= ((int) ($record['RouteByDataObjectHierarchy'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="workflowRouteByHierarchy"><?= h(__t('route_by_dataobject_hierarchy')) ?></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="workflowDefinitionActive" name="ActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="workflowDefinitionActive"><?= h(__t('active')) ?></label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <div class="small text-muted"><?= h(__t('workflow_engine_stage_setup_help')) ?></div>
            <div class="d-flex gap-2">
              <?php if ($record !== null && ((int) ($record['ActiveFlag'] ?? 1) === 1)): ?>
                <button type="submit" id="workflow-engine-save-btn" class="btn btn-primary"><?= h(__t('save_definition')) ?></button>
              <?php else: ?>
                <button type="submit" id="workflow-engine-save-btn" class="btn btn-primary"><?= h(__t('save_definition')) ?></button>
              <?php endif; ?>
            </div>
          </div>
        </form>

        <?php if ($workflowAreaCode === ''): ?>
          <div class="alert alert-info border-0 shadow-sm mb-0">
            <?= h(__t('workflow_engine_save_definition_first')) ?>
          </div>
        <?php else: ?>
          <div class="row g-4">
            <div class="col-12 col-xl-6">
              <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Stages</h5>
                  <a href="index.php?route=workflow-engine/stage-form&workflow_area_code=<?= urlencode($workflowAreaCode) ?>" id="workflow-engine-add-stage-btn" class="btn btn-sm btn-primary">Add Stage</a>
                </div>
                <div class="card-body">
                  <?php if ($stageRows === []): ?>
                    <div class="text-muted">No stages are configured for this workflow yet.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="workflow-engine-stage-table">
                        <thead class="table-light">
                          <tr>
                            <th>Order</th>
                            <th>Stage</th>
                            <th>Type</th>
                            <th>Flags</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($stageRows as $stageRow): ?>
                            <?php
                              $flags = [];
                              if ((int) ($stageRow['IsDraftStage'] ?? 0) === 1) {
                                  $flags[] = 'Draft';
                              }
                              if ((int) ($stageRow['IsFinalStage'] ?? 0) === 1) {
                                  $flags[] = 'Final';
                              }
                              if ((int) ($stageRow['RequireDifferentActorFromPreviousStage'] ?? 0) === 1) {
                                  $flags[] = 'Maker-checker';
                              }
                            ?>
                            <tr>
                              <td><?= (int) ($stageRow['StageOrder'] ?? 0) ?></td>
                              <td>
                                <div class="fw-semibold"><?= h((string) ($stageRow['WorkflowStageName'] ?? '')) ?></div>
                                <div class="small text-muted"><?= h((string) ($stageRow['WorkflowStageCode'] ?? '')) ?></div>
                              </td>
                              <td><?= h((string) ($stageRow['StageType'] ?? '')) ?></td>
                              <td>
                                <div><?= h($flags !== [] ? implode(' / ', $flags) : '-') ?></div>
                                <div class="small text-muted"><?= ((int) ($stageRow['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></div>
                              </td>
                              <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                  <a class="btn btn-outline-primary" id="workflow-engine-stage-edit-btn-<?= (int) ($stageRow['WorkflowDefinitionStageID'] ?? 0) ?>" href="index.php?route=workflow-engine/stage-form&id=<?= (int) ($stageRow['WorkflowDefinitionStageID'] ?? 0) ?>">Edit</a>
                                  <form method="post" action="index.php?route=workflow-engine/archive-stage" id="workflow-engine-stage-archive-form-<?= (int) ($stageRow['WorkflowDefinitionStageID'] ?? 0) ?>" onsubmit="return confirm('Archive this workflow stage?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="WorkflowDefinitionStageID" value="<?= (int) ($stageRow['WorkflowDefinitionStageID'] ?? 0) ?>">
                                    <input type="hidden" name="WorkflowAreaCode" value="<?= h($workflowAreaCode) ?>">
                                    <button type="submit" id="workflow-engine-stage-archive-btn-<?= (int) ($stageRow['WorkflowDefinitionStageID'] ?? 0) ?>" class="btn btn-outline-danger">Archive</button>
                                  </form>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-6">
              <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Actions</h5>
                  <a href="index.php?route=workflow-engine/action-form&workflow_area_code=<?= urlencode($workflowAreaCode) ?>" id="workflow-engine-add-action-btn" class="btn btn-sm btn-primary">Add Action</a>
                </div>
                <div class="card-body">
                  <?php if ($actionRows === []): ?>
                    <div class="text-muted">No actions are configured for this workflow yet.</div>
                  <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="workflow-engine-action-table">
                        <thead class="table-light">
                          <tr>
                            <th>Action</th>
                            <th>Transition</th>
                            <th>Type</th>
                            <th class="text-end">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($actionRows as $actionRow): ?>
                            <tr>
                              <td>
                                <div class="fw-semibold"><?= h((string) ($actionRow['WorkflowActionName'] ?? '')) ?></div>
                                <div class="small text-muted"><?= h((string) ($actionRow['WorkflowActionCode'] ?? '')) ?></div>
                              </td>
                              <td>
                                <?= h((string) ($actionRow['FromStageCode'] ?? '')) ?>
                                <span class="text-muted mx-1">to</span>
                                <?= h((string) ($actionRow['ToStageCode'] ?? '')) ?>
                              </td>
                              <td>
                                <div><?= h((string) ($actionRow['ActionType'] ?? '')) ?></div>
                                <div class="small text-muted"><?= ((int) ($actionRow['RequireNote'] ?? 0) === 1) ? 'Note required' : 'No note required' ?></div>
                              </td>
                              <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                  <a class="btn btn-outline-primary" id="workflow-engine-action-edit-btn-<?= (int) ($actionRow['WorkflowDefinitionActionID'] ?? 0) ?>" href="index.php?route=workflow-engine/action-form&id=<?= (int) ($actionRow['WorkflowDefinitionActionID'] ?? 0) ?>">Edit</a>
                                  <form method="post" action="index.php?route=workflow-engine/archive-action" id="workflow-engine-action-archive-form-<?= (int) ($actionRow['WorkflowDefinitionActionID'] ?? 0) ?>" onsubmit="return confirm('Archive this workflow action?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="WorkflowDefinitionActionID" value="<?= (int) ($actionRow['WorkflowDefinitionActionID'] ?? 0) ?>">
                                    <input type="hidden" name="WorkflowAreaCode" value="<?= h($workflowAreaCode) ?>">
                                    <button type="submit" id="workflow-engine-action-archive-btn-<?= (int) ($actionRow['WorkflowDefinitionActionID'] ?? 0) ?>" class="btn btn-outline-danger">Archive</button>
                                  </form>
                                </div>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4 d-flex justify-content-between align-items-center">
            <div class="small text-muted">Archiving a definition leaves history intact and prevents new use after related routing is updated.</div>
            <?php if ((int) ($record['ActiveFlag'] ?? 1) === 1): ?>
              <form method="post" action="index.php?route=workflow-engine/archive" id="workflow-engine-archive-form" onsubmit="return confirm('Archive this workflow definition?');">
                <?= csrf_field() ?>
                <input type="hidden" name="WorkflowAreaCode" value="<?= h($workflowAreaCode) ?>">
                <button type="submit" id="workflow-engine-archive-btn" class="btn btn-outline-danger">Archive Definition</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
