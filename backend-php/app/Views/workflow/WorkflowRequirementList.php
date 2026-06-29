<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wf_requirement_plain')) {
    function wf_requirement_plain($value): string
    {
        if (function_exists('workflow_rich_text_to_plain_text')) {
            return workflow_rich_text_to_plain_text((string)$value);
        }
        return trim(strip_tags((string)$value));
    }
}

if (!function_exists('wf_requirement_excerpt')) {
    function wf_requirement_excerpt($value, int $length = 130): string
    {
        $text = wf_requirement_plain($value);
        if (strlen($text) <= $length) {
            return $text;
        }
        return rtrim(substr($text, 0, $length - 3)) . '...';
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$deliveryClassOptions = is_array($deliveryClassOptions ?? null) ? $deliveryClassOptions : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$priorityOptions = is_array($priorityOptions ?? null) ? $priorityOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$requirementLevelOptions = is_array($requirementLevelOptions ?? null) ? $requirementLevelOptions : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$tableInstalled = !empty($tableInstalled);

$activeCount = 0;
$mustCount = 0;
$approvedCount = 0;
$highLevelCount = 0;
$detailedCount = 0;
foreach ($rows as $row) {
    if ((int)($row['Active'] ?? 0) === 1) {
        $activeCount++;
    }
    if (strtoupper((string)($row['PriorityCode'] ?? '')) === 'MUST') {
        $mustCount++;
    }
    if (strtoupper((string)($row['RequirementStatusCode'] ?? '')) === 'APPROVED') {
        $approvedCount++;
    }
    if (strtoupper((string)($row['RequirementLevelCode'] ?? 'HIGH_LEVEL')) === 'DETAILED') {
        $detailedCount++;
    } else {
        $highLevelCount++;
    }
}

$labelFor = static function (array $options, ?string $code): string {
    $code = strtoupper(trim((string)$code));
    $key = $options[$code] ?? '';
    return $key !== '' ? __t($key) : $code;
};

$screenHeader = [
    'title' => __t('workflow_requirements'),
    'icon' => 'bi-journal-richtext',
];
$workflowRequirementReturnTo = 'index.php?route=workflow-requirements/list';
$workflowRequirementQueryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
if ($workflowRequirementQueryString !== '') {
    $workflowRequirementReturnTo = 'index.php?' . $workflowRequirementQueryString;
}
$workflowRequirementReturnParam = rawurlencode($workflowRequirementReturnTo);
?>

<style>
  .workflow-requirement-tree-table tbody tr.workflow-requirement-parent-row {
    border-top: 2px solid #e8edf5;
  }
  .workflow-requirement-tree-table tbody tr.workflow-requirement-parent-row:first-child {
    border-top-width: 0;
  }
  .workflow-requirement-parent-row > td:first-child {
    background: #fbfcff;
  }
  .workflow-requirement-detail-row > td:first-child {
    padding-left: 1.35rem;
  }
  .workflow-requirement-tree-marker {
    align-items: center;
    color: #6c757d;
    display: inline-flex;
    flex: 0 0 1.15rem;
    justify-content: center;
    width: 1.15rem;
  }
  .workflow-requirement-detail-row .workflow-requirement-tree-marker {
    color: #0d6efd;
  }
  .workflow-requirement-tree-content {
    min-width: 0;
  }
  .workflow-requirement-hierarchy-badge {
    font-weight: 500;
  }
</style>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (is_array($flash ?? null) && !empty($flash['text'])): ?>
        <div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= $flash['text'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          <?= h(__t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <div class="text-muted small"><?= h(__t('workflow_requirement_register_help')) ?></div>
          <div class="d-flex flex-wrap gap-3 mt-2">
            <span><strong><?= count($rows) ?></strong> <?= h(__t('workflow_requirements')) ?></span>
            <span><strong><?= $activeCount ?></strong> <?= h(__t('workflow_requirement_active_count')) ?></span>
            <span><strong><?= $mustCount ?></strong> <?= h(__t('workflow_requirement_must_have')) ?></span>
            <span><strong><?= $approvedCount ?></strong> <?= h(__t('workflow_requirement_approved_count')) ?></span>
            <span><strong><?= $highLevelCount ?></strong> <?= h(__t('workflow_requirement_level_high_level')) ?></span>
            <span><strong><?= $detailedCount ?></strong> <?= h(__t('workflow_requirement_level_detailed')) ?></span>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="index.php?route=workflow-requirements/matrix<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>" class="btn btn-sm btn-outline-secondary <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-diagram-3 me-1"></i><?= h(__t('workflow_requirement_matrix')) ?>
          </a>
          <a href="index.php?route=workflow-requirements/summary<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>" class="btn btn-sm btn-outline-info <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-speedometer2 me-1"></i><?= h(__t('workflow_requirement_summary')) ?>
          </a>
          <a href="index.php?route=workflow-requirements/form<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>&returnTo=<?= $workflowRequirementReturnParam ?>" class="btn btn-sm btn-primary <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_requirement_create')) ?>
          </a>
        </div>
      </div>

      <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="route" value="workflow-requirements/list">
        <div class="col-12 col-lg-3">
          <label class="form-label" for="workflowRequirementSearch"><?= h(__t('search')) ?></label>
          <input class="form-control" type="text" id="workflowRequirementSearch" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="<?= h(__t('workflow_requirement_search_placeholder')) ?>">
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label" for="workflowRequirementProject"><?= h(__t('workflow_project_project')) ?></label>
          <select class="form-select" id="workflowRequirementProject" name="workflowProjectID">
            <option value=""><?= h(__t('workflow_requirement_all_projects')) ?></option>
            <?php foreach ($workflowProjects as $project): ?>
              <?php $projectId = (int)($project['WorkflowProjectID'] ?? 0); ?>
              <?php if ($projectId <= 0) { continue; } ?>
              <option value="<?= $projectId ?>" <?= (int)($filters['workflowProjectID'] ?? 0) === $projectId ? 'selected' : '' ?>>
                <?= h((string)($project['ProjectName'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementDeliveryClass"><?= h(__t('workflow_requirement_delivery_class')) ?></label>
          <select class="form-select" id="workflowRequirementDeliveryClass" name="deliveryClass">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($deliveryClassOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['deliveryClass'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementType"><?= h(__t('workflow_requirement_type')) ?></label>
          <select class="form-select" id="workflowRequirementType" name="type">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($typeOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['type'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementPriority"><?= h(__t('workflow_requirement_priority')) ?></label>
          <select class="form-select" id="workflowRequirementPriority" name="priority">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($priorityOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['priority'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementStatus"><?= h(__t('status')) ?></label>
          <select class="form-select" id="workflowRequirementStatus" name="status">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($statusOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementLevel"><?= h(__t('workflow_requirement_level')) ?></label>
          <select class="form-select" id="workflowRequirementLevel" name="requirementLevel">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($requirementLevelOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['requirementLevel'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementActive"><?= h(__t('workflow_project_active_filter')) ?></label>
          <select class="form-select" id="workflowRequirementActive" name="active">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>><?= h(__t('workflow_requirement_all_requirements')) ?></option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>><?= h(__t('workflow_project_active_only')) ?></option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>><?= h(__t('workflow_project_inactive_only')) ?></option>
          </select>
        </div>
        <div class="col-6 col-lg-1 d-grid">
          <button type="submit" class="btn btn-sm btn-outline-primary"><?= h(__t('filter')) ?></button>
        </div>
        <div class="col-6 col-lg-1 d-grid">
          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-requirements/list"><?= h(__t('reset')) ?></a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 workflow-requirement-tree-table">
          <thead class="table-light">
            <tr>
              <th><?= h(__t('workflow_requirement')) ?></th>
              <th><?= h(__t('workflow_project_project')) ?></th>
              <th><?= h(__t('workflow_requirement_delivery_class')) ?></th>
              <th><?= h(__t('workflow_requirement_type')) ?></th>
              <th><?= h(__t('workflow_requirement_priority')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th><?= h(__t('workflow_requirement_owner')) ?></th>
              <th class="text-end"><?= h(__t('actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3"><?= h(__t('workflow_requirement_none_found')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $id = (int)($row['WorkflowRequirementID'] ?? 0);
                  $statusCode = strtoupper((string)($row['RequirementStatusCode'] ?? ''));
                  $priorityCode = strtoupper((string)($row['PriorityCode'] ?? ''));
                  $levelCode = strtoupper((string)($row['RequirementLevelCode'] ?? 'HIGH_LEVEL'));
                  $parentRequirementId = (int)($row['ParentRequirementID'] ?? 0);
                  $isDetailedRequirement = $levelCode === 'DETAILED';
                  $childRequirementCount = (int)($row['ChildRequirementCount'] ?? 0);
                  $isInactive = (int)($row['Active'] ?? 0) !== 1;
                ?>
                <tr class="<?= $isDetailedRequirement ? 'workflow-requirement-detail-row' : 'workflow-requirement-parent-row' ?>">
                  <td>
                    <div class="d-flex align-items-start gap-2">
                      <span class="workflow-requirement-tree-marker mt-1" aria-hidden="true">
                        <i class="bi <?= $isDetailedRequirement ? 'bi-arrow-return-right' : 'bi-diagram-2' ?>"></i>
                      </span>
                      <div class="workflow-requirement-tree-content">
                        <div class="fw-semibold">
                          <a href="index.php?route=workflow-requirements/form&id=<?= $id ?>&returnTo=<?= $workflowRequirementReturnParam ?>"><?= h((string)($row['RequirementTitle'] ?? '')) ?></a>
                          <span class="badge <?= $isDetailedRequirement ? 'text-bg-secondary' : 'text-bg-info' ?> workflow-requirement-hierarchy-badge ms-1">
                            <?= h($labelFor($requirementLevelOptions, $levelCode)) ?>
                          </span>
                          <?php if (!$isDetailedRequirement && $childRequirementCount > 0): ?>
                            <span class="badge text-bg-light border workflow-requirement-hierarchy-badge ms-1">
                              <?= $childRequirementCount ?> <?= h(__t('workflow_requirement_level_detailed')) ?>
                            </span>
                          <?php endif; ?>
                        </div>
                        <div class="text-muted small">
                          <?= h((string)($row['RequirementCode'] ?? __t('workflow_requirement_no_code'))) ?>
                          <?php if (!empty($row['ModuleCode'])): ?>
                            <span class="mx-1">-</span><?= h((string)$row['ModuleCode']) ?>
                          <?php endif; ?>
                        </div>
                        <?php if ($parentRequirementId > 0): ?>
                          <div class="small">
                            <span class="text-muted"><?= h(__t('workflow_requirement_parent')) ?>:</span>
                            <a href="index.php?route=workflow-requirements/form&id=<?= $parentRequirementId ?>&returnTo=<?= $workflowRequirementReturnParam ?>">
                              <?= h(trim((string)($row['ParentRequirementCode'] ?? '')) !== '' ? (string)$row['ParentRequirementCode'] . ' - ' . (string)($row['ParentRequirementTitle'] ?? '') : (string)($row['ParentRequirementTitle'] ?? '')) ?>
                            </a>
                          </div>
                        <?php endif; ?>
                        <?php if (!empty($row['SourceDocument']) || !empty($row['SourceSection'])): ?>
                          <div class="text-muted small">
                            <?= h((string)($row['SourceDocument'] ?? '')) ?>
                            <?php if (!empty($row['SourceSection'])): ?>
                              <span class="mx-1">/</span><?= h((string)$row['SourceSection']) ?>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>
                        <?php $excerpt = wf_requirement_excerpt($row['Description'] ?? ''); ?>
                        <?php if ($excerpt !== ''): ?>
                          <div class="small text-muted mt-1"><?= h($excerpt) ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="small">
                    <?php if (!empty($row['WorkflowProjectID'])): ?>
                      <a href="index.php?route=workflow-projects/summary&id=<?= (int)$row['WorkflowProjectID'] ?>"><?= h((string)($row['ProjectName'] ?? '')) ?></a>
                    <?php else: ?>
                      <span class="text-muted"><?= h(__t('workflow_project_no_project')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= h($labelFor($deliveryClassOptions, (string)($row['DeliveryClassCode'] ?? ''))) ?></td>
                  <td><?= h($labelFor($typeOptions, (string)($row['RequirementTypeCode'] ?? ''))) ?></td>
                  <td>
                    <span class="badge text-bg-<?= $priorityCode === 'MUST' ? 'danger' : ($priorityCode === 'SHOULD' ? 'primary' : 'secondary') ?>">
                      <?= h($labelFor($priorityOptions, $priorityCode)) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= in_array($statusCode, ['APPROVED', 'COMPLETED'], true) ? 'success' : ($statusCode === 'CANCELLED' ? 'secondary' : 'info') ?>">
                      <?= h($labelFor($statusOptions, $statusCode)) ?>
                    </span>
                    <?php if ($isInactive): ?>
                      <div class="text-muted small"><?= h(__t('workflow_project_inactive')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)($row['OwnerName'] ?? '')) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow-requirements/form&id=<?= $id ?>&returnTo=<?= $workflowRequirementReturnParam ?>"><?= h(__t('edit')) ?></a>
                    <?php if (!$isDetailedRequirement): ?>
                      <a class="btn btn-sm btn-outline-success" href="index.php?route=workflow-requirements/form&parentRequirementID=<?= $id ?>&returnTo=<?= $workflowRequirementReturnParam ?>"><?= h(__t('workflow_requirement_add_child')) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($row['WorkflowProjectID'])): ?>
                      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow/list&workflowProjectID=<?= (int)$row['WorkflowProjectID'] ?>"><?= h(__t('workflow_project_view_tasks')) ?></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
