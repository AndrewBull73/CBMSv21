<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wf_requirement_matrix_label')) {
    function wf_requirement_matrix_label(array $options, ?string $code): string
    {
        $code = strtoupper(trim((string)$code));
        $key = $options[$code] ?? '';
        return $key !== '' ? __t($key) : $code;
    }
}

if (!function_exists('wf_requirement_matrix_date')) {
    function wf_requirement_matrix_date($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('Y-m-d', $ts);
    }
}

if (!function_exists('wf_requirement_matrix_task_titles')) {
    function wf_requirement_matrix_task_titles($value): array
    {
        $text = trim((string)$value);
        if ($text === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode('; ', $text)), static fn(string $item): bool => $item !== ''));
    }
}

if (!function_exists('wf_requirement_matrix_gap_label')) {
    function wf_requirement_matrix_gap_label(string $code): string
    {
        $key = 'workflow_requirement_matrix_gap_' . strtolower($code);
        return __t($key) === $key ? str_replace('_', ' ', $code) : __t($key);
    }
}

if (!function_exists('wf_requirement_matrix_gap_class')) {
    function wf_requirement_matrix_gap_class(string $code): string
    {
        return match (strtoupper($code)) {
            'NEEDS_TASK', 'MISSING_ACCEPTANCE', 'NO_TESTING' => 'text-bg-danger',
            'OPEN_TASKS', 'NO_TRAINING' => 'text-bg-warning',
            'HAS_DEFECTS' => 'text-bg-dark',
            default => 'text-bg-secondary',
        };
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$filters = is_array($filters ?? null) ? $filters : [];
$coverageOptions = is_array($coverageOptions ?? null) ? $coverageOptions : [];
$deliveryClassOptions = is_array($deliveryClassOptions ?? null) ? $deliveryClassOptions : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$priorityOptions = is_array($priorityOptions ?? null) ? $priorityOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$requirementLevelOptions = is_array($requirementLevelOptions ?? null) ? $requirementLevelOptions : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$tableInstalled = !empty($tableInstalled);
$workflowLinksInstalled = !empty($workflowLinksInstalled);
$canCreateRequirement = !empty($canCreateRequirement);
$canCreateWorkflowTask = !empty($canCreateWorkflowTask);

$metricPanels = [
    ['label' => __t('workflow_requirement_total'), 'value' => (int)($summary['total'] ?? 0), 'class' => 'text-bg-primary'],
    ['label' => __t('workflow_requirement_matrix_missing_task'), 'value' => (int)($summary['missingTask'] ?? 0), 'class' => 'text-bg-danger'],
    ['label' => __t('workflow_requirement_matrix_with_open_tasks'), 'value' => (int)($summary['withOpenTasks'] ?? 0), 'class' => 'text-bg-warning'],
    ['label' => __t('workflow_requirement_matrix_missing_testing'), 'value' => (int)($summary['missingTesting'] ?? 0), 'class' => 'text-bg-danger'],
    ['label' => __t('workflow_requirement_missing_acceptance'), 'value' => (int)($summary['missingAcceptanceCriteria'] ?? 0), 'class' => 'text-bg-warning'],
    ['label' => __t('workflow_requirement_matrix_with_defects'), 'value' => (int)($summary['withDefects'] ?? 0), 'class' => 'text-bg-dark'],
];

$screenHeader = [
    'title' => __t('workflow_requirement_matrix'),
    'icon' => 'bi-diagram-3',
];
$workflowRequirementMatrixReturnTo = 'index.php?route=workflow-requirements/matrix';
$workflowRequirementMatrixQueryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
if ($workflowRequirementMatrixQueryString !== '') {
    $workflowRequirementMatrixReturnTo = 'index.php?' . $workflowRequirementMatrixQueryString;
}
$workflowRequirementMatrixReturnParam = rawurlencode($workflowRequirementMatrixReturnTo);
?>

<style>
  .requirement-matrix-table {
    min-width: 1220px;
  }
  .requirement-matrix-title {
    max-width: 30rem;
  }
  .requirement-matrix-task-list {
    max-width: 24rem;
  }
  .requirement-matrix-badge-group {
    row-gap: .25rem;
  }
</style>

<div class="container-fluid mt-4">
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
      <?php elseif (!$workflowLinksInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          <?= h(__t('workflow_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div class="text-muted small"><?= h(__t('workflow_requirement_matrix_help')) ?></div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <?php if ($canCreateRequirement): ?>
            <a href="index.php?route=workflow-requirements/form<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>&returnTo=<?= $workflowRequirementMatrixReturnParam ?>" class="btn btn-sm btn-primary <?= !$tableInstalled ? 'disabled' : '' ?>">
              <i class="bi bi-plus-lg me-1"></i><?= h(__t('workflow_requirement_create')) ?>
            </a>
          <?php endif; ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary" type="button" id="workflowRequirementMatrixActions" data-bs-toggle="dropdown" aria-expanded="false" title="<?= h(__t('actions')) ?>" aria-label="<?= h(__t('actions')) ?>">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="workflowRequirementMatrixActions">
              <li>
                <a class="dropdown-item" href="index.php?route=workflow-requirements/list<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>">
                  <i class="bi bi-list-ul me-2"></i><?= h(__t('workflow_requirements')) ?>
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="index.php?route=workflow-requirements/summary<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>">
                  <i class="bi bi-speedometer2 me-2"></i><?= h(__t('workflow_requirement_summary')) ?>
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="route" value="workflow-requirements/matrix">
        <div class="col-12 col-xl-3">
          <label class="form-label" for="workflowRequirementMatrixSearch"><?= h(__t('search')) ?></label>
          <input class="form-control" type="text" id="workflowRequirementMatrixSearch" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="<?= h(__t('workflow_requirement_search_placeholder')) ?>">
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <label class="form-label" for="workflowRequirementMatrixProject"><?= h(__t('workflow_project_project')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixProject" name="workflowProjectID">
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
        <div class="col-12 col-md-6 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixCoverage"><?= h(__t('workflow_requirement_matrix_coverage_filter')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixCoverage" name="coverage">
            <?php foreach ($coverageOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['coverage'] ?? 'ALL')) === (string)$code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixDeliveryClass"><?= h(__t('workflow_requirement_delivery_class')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixDeliveryClass" name="deliveryClass">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($deliveryClassOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['deliveryClass'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixStatus"><?= h(__t('status')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixStatus" name="status">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($statusOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixType"><?= h(__t('workflow_requirement_type')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixType" name="type">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($typeOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['type'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixPriority"><?= h(__t('workflow_requirement_priority')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixPriority" name="priority">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($priorityOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['priority'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixLevel"><?= h(__t('workflow_requirement_level')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixLevel" name="requirementLevel">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($requirementLevelOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['requirementLevel'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
          <label class="form-label" for="workflowRequirementMatrixActive"><?= h(__t('workflow_project_active_filter')) ?></label>
          <select class="form-select" id="workflowRequirementMatrixActive" name="active">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>><?= h(__t('workflow_requirement_all_requirements')) ?></option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>><?= h(__t('workflow_project_active_only')) ?></option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>><?= h(__t('workflow_project_inactive_only')) ?></option>
          </select>
        </div>
        <div class="col-6 col-md-3 col-xl-1 d-grid">
          <button type="submit" class="btn btn-sm btn-outline-primary"><?= h(__t('filter')) ?></button>
        </div>
        <div class="col-6 col-md-3 col-xl-1 d-grid">
          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-requirements/matrix"><?= h(__t('reset')) ?></a>
        </div>
      </form>

      <div class="row g-2 mb-3">
        <?php foreach ($metricPanels as $panel): ?>
          <div class="col-6 col-md-4 col-xl-2">
            <div class="border rounded-2 px-3 py-2 h-100">
              <div class="text-muted small"><?= h((string)$panel['label']) ?></div>
              <div class="d-flex justify-content-between align-items-end mt-1">
                <div class="fs-4 fw-semibold"><?= (int)$panel['value'] ?></div>
                <span class="badge <?= h((string)$panel['class']) ?>">&nbsp;</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 requirement-matrix-table">
          <thead class="table-light">
            <tr>
              <th><?= h(__t('workflow_requirement')) ?></th>
              <th><?= h(__t('workflow_project_project')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th><?= h(__t('workflow_requirement_matrix_linked_tasks')) ?></th>
              <th><?= h(__t('workflow_requirement_matrix_evidence')) ?></th>
              <th><?= h(__t('workflow_requirement_matrix_gaps')) ?></th>
              <th class="text-end"><?= h(__t('actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-3"><?= h(__t('workflow_requirement_none_found')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $id = (int)($row['WorkflowRequirementID'] ?? 0);
                  $projectId = (int)($row['WorkflowProjectID'] ?? 0);
                  $statusCode = strtoupper((string)($row['RequirementStatusCode'] ?? ''));
                  $priorityCode = strtoupper((string)($row['PriorityCode'] ?? ''));
                  $levelCode = strtoupper((string)($row['RequirementLevelCode'] ?? 'HIGH_LEVEL'));
                  $parentRequirementId = (int)($row['ParentRequirementID'] ?? 0);
                  $gapCodes = is_array($row['TraceabilityGapCodes'] ?? null) ? $row['TraceabilityGapCodes'] : [];
                  $taskTitles = wf_requirement_matrix_task_titles($row['LinkedTaskTitles'] ?? '');
                  $visibleTaskTitles = array_slice($taskTitles, 0, 3);
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold requirement-matrix-title">
                      <a href="index.php?route=workflow-requirements/form&id=<?= $id ?>&returnTo=<?= $workflowRequirementMatrixReturnParam ?>"><?= h((string)($row['RequirementTitle'] ?? '')) ?></a>
                    </div>
                    <div class="text-muted small">
                      <?= h((string)($row['RequirementCode'] ?? __t('workflow_requirement_no_code'))) ?>
                      <span class="mx-1">-</span><?= h(wf_requirement_matrix_label($requirementLevelOptions, $levelCode)) ?>
                      <?php if (!empty($row['ModuleCode'])): ?>
                        <span class="mx-1">-</span><?= h((string)$row['ModuleCode']) ?>
                      <?php endif; ?>
                    </div>
                    <?php if ($parentRequirementId > 0): ?>
                      <div class="small">
                        <span class="text-muted"><?= h(__t('workflow_requirement_parent')) ?>:</span>
                        <a href="index.php?route=workflow-requirements/form&id=<?= $parentRequirementId ?>&returnTo=<?= $workflowRequirementMatrixReturnParam ?>">
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
                  </td>
                  <td class="small">
                    <?php if ($projectId > 0): ?>
                      <a href="index.php?route=workflow-projects/summary&id=<?= $projectId ?>"><?= h((string)($row['ProjectName'] ?? '')) ?></a>
                      <?php if (!empty($row['ProjectCode'])): ?>
                        <div class="text-muted"><?= h((string)$row['ProjectCode']) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted"><?= h(__t('workflow_project_no_project')) ?></span>
                    <?php endif; ?>
                    <div class="text-muted"><?= h(wf_requirement_matrix_label($deliveryClassOptions, (string)($row['DeliveryClassCode'] ?? ''))) ?></div>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= in_array($statusCode, ['APPROVED', 'COMPLETED'], true) ? 'success' : ($statusCode === 'CANCELLED' ? 'secondary' : 'info') ?>">
                      <?= h(wf_requirement_matrix_label($statusOptions, $statusCode)) ?>
                    </span>
                    <div class="mt-1">
                      <span class="badge text-bg-<?= $priorityCode === 'MUST' ? 'danger' : ($priorityCode === 'SHOULD' ? 'primary' : 'secondary') ?>">
                        <?= h(wf_requirement_matrix_label($priorityOptions, $priorityCode)) ?>
                      </span>
                    </div>
                    <?php if (!empty($row['OwnerName'])): ?>
                      <div class="text-muted small mt-1"><?= h((string)$row['OwnerName']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap gap-1 requirement-matrix-badge-group mb-1">
                      <span class="badge text-bg-primary"><?= h(__t('workflow_requirement_matrix_tasks_short', ['count' => (int)($row['TaskLinkCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-warning"><?= h(__t('workflow_requirement_matrix_open_short', ['count' => (int)($row['OpenTaskCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-success"><?= h(__t('workflow_requirement_matrix_closed_short', ['count' => (int)($row['ClosedTaskCount'] ?? 0)])) ?></span>
                    </div>
                    <?php if (!empty($row['NextOpenTaskDueDate'])): ?>
                      <div class="small text-muted"><?= h(__t('workflow_requirement_matrix_next_due')) ?>: <?= h(wf_requirement_matrix_date($row['NextOpenTaskDueDate'])) ?></div>
                    <?php endif; ?>
                    <?php if ($visibleTaskTitles !== []): ?>
                      <div class="small requirement-matrix-task-list">
                        <?php foreach ($visibleTaskTitles as $title): ?>
                          <div><?= h($title) ?></div>
                        <?php endforeach; ?>
                        <?php if (count($taskTitles) > count($visibleTaskTitles)): ?>
                          <div class="text-muted"><?= h(__t('workflow_requirement_matrix_more_tasks', ['count' => count($taskTitles) - count($visibleTaskTitles)])) ?></div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap gap-1 requirement-matrix-badge-group">
                      <span class="badge text-bg-info"><?= h(__t('workflow_requirement_matrix_test_short', ['count' => (int)($row['TestingLinkCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-secondary"><?= h(__t('workflow_requirement_matrix_train_short', ['count' => (int)($row['TrainingLinkCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-dark"><?= h(__t('workflow_requirement_matrix_defect_short', ['count' => (int)($row['DefectLinkCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-light border text-dark"><?= h(__t('workflow_requirement_matrix_doc_short', ['count' => (int)($row['DocumentationLinkCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-light border text-dark"><?= h(__t('workflow_requirement_matrix_release_short', ['count' => (int)($row['ReleaseLinkCount'] ?? 0)])) ?></span>
                      <span class="badge text-bg-light border text-dark"><?= h(__t('workflow_requirement_matrix_attachment_short', ['count' => (int)($row['AttachmentCount'] ?? 0)])) ?></span>
                    </div>
                  </td>
                  <td>
                    <?php if ($gapCodes === []): ?>
                      <span class="badge text-bg-success"><?= h(__t('workflow_requirement_matrix_no_gaps')) ?></span>
                    <?php else: ?>
                      <div class="d-flex flex-wrap gap-1 requirement-matrix-badge-group">
                        <?php foreach ($gapCodes as $gapCode): ?>
                          <span class="badge <?= h(wf_requirement_matrix_gap_class((string)$gapCode)) ?>"><?= h(wf_requirement_matrix_gap_label((string)$gapCode)) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1 align-items-center">
                      <?php if ($projectId > 0 && $canCreateWorkflowTask): ?>
                        <a class="btn btn-sm btn-outline-success" href="index.php?route=workflow/edit&workflowProjectID=<?= $projectId ?>&workflowRequirementID=<?= $id ?>" title="<?= h(__t('workflow_project_create_task')) ?>" aria-label="<?= h(__t('workflow_project_create_task')) ?>">
                          <i class="bi bi-plus-lg me-1"></i><?= h(__t('workflow_project_task')) ?>
                        </a>
                      <?php endif; ?>
                      <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="workflowRequirementMatrixRowActions<?= $id ?>" data-bs-toggle="dropdown" aria-expanded="false" title="<?= h(__t('actions')) ?>" aria-label="<?= h(__t('actions')) ?>">
                          <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="workflowRequirementMatrixRowActions<?= $id ?>">
                          <li>
                            <a class="dropdown-item" href="index.php?route=workflow-requirements/form&id=<?= $id ?>&returnTo=<?= $workflowRequirementMatrixReturnParam ?>">
                              <i class="bi bi-box-arrow-up-right me-2"></i><?= h(__t('open')) ?>
                            </a>
                          </li>
                          <?php if ($projectId > 0): ?>
                            <li>
                              <a class="dropdown-item" href="index.php?route=workflow/list&workflowProjectID=<?= $projectId ?>">
                                <i class="bi bi-list-task me-2"></i><?= h(__t('workflow_project_view_tasks')) ?>
                              </a>
                            </li>
                          <?php endif; ?>
                        </ul>
                      </div>
                    </div>
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
