<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wf_requirement_summary_label')) {
    function wf_requirement_summary_label(array $options, ?string $code): string
    {
        $code = strtoupper(trim((string)$code));
        $key = $options[$code] ?? '';
        return $key !== '' ? __t($key) : $code;
    }
}

if (!function_exists('wf_requirement_summary_datetime')) {
    function wf_requirement_summary_datetime($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('Y-m-d H:i', $ts);
    }
}

$summary = is_array($summary ?? null) ? $summary : [];
$filters = is_array($filters ?? null) ? $filters : [];
$deliveryClassOptions = is_array($deliveryClassOptions ?? null) ? $deliveryClassOptions : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$priorityOptions = is_array($priorityOptions ?? null) ? $priorityOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$requirementLevelOptions = is_array($requirementLevelOptions ?? null) ? $requirementLevelOptions : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$tableInstalled = !empty($tableInstalled);

$byStatus = is_array($summary['byStatus'] ?? null) ? $summary['byStatus'] : [];
$byLevel = is_array($summary['byLevel'] ?? null) ? $summary['byLevel'] : [];
$byPriority = is_array($summary['byPriority'] ?? null) ? $summary['byPriority'] : [];
$byType = is_array($summary['byType'] ?? null) ? $summary['byType'] : [];
$byDeliveryClass = is_array($summary['byDeliveryClass'] ?? null) ? $summary['byDeliveryClass'] : [];
$byProject = is_array($summary['byProject'] ?? null) ? $summary['byProject'] : [];
$parentCoverage = is_array($summary['parentCoverage'] ?? null) ? $summary['parentCoverage'] : [];
$parentCoverageSummary = is_array($summary['parentCoverageSummary'] ?? null) ? $summary['parentCoverageSummary'] : [];
$recent = is_array($summary['recent'] ?? null) ? $summary['recent'] : [];

$metricPanels = [
    ['label' => __t('workflow_requirement_total'), 'value' => (int)($summary['total'] ?? 0), 'class' => 'text-bg-primary'],
    ['label' => __t('workflow_requirement_active_count'), 'value' => (int)($summary['active'] ?? 0), 'class' => 'text-bg-success'],
    ['label' => __t('workflow_requirement_level_high_level'), 'value' => (int)($summary['highLevel'] ?? 0), 'class' => 'text-bg-info'],
    ['label' => __t('workflow_requirement_level_detailed'), 'value' => (int)($summary['detailed'] ?? 0), 'class' => 'text-bg-secondary'],
    ['label' => __t('workflow_requirement_missing_owner'), 'value' => (int)($summary['missingOwner'] ?? 0), 'class' => 'text-bg-warning'],
    ['label' => __t('workflow_requirement_missing_acceptance'), 'value' => (int)($summary['missingAcceptanceCriteria'] ?? 0), 'class' => 'text-bg-danger'],
];

$screenHeader = [
    'title' => __t('workflow_requirement_summary'),
    'icon' => 'bi-speedometer2',
];
$workflowRequirementSummaryReturnTo = 'index.php?route=workflow-requirements/summary';
$workflowRequirementSummaryQueryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
if ($workflowRequirementSummaryQueryString !== '') {
    $workflowRequirementSummaryReturnTo = 'index.php?' . $workflowRequirementSummaryQueryString;
}
$workflowRequirementSummaryReturnParam = rawurlencode($workflowRequirementSummaryReturnTo);
?>

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

      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div class="text-muted small"><?= h(__t('workflow_requirement_summary_help')) ?></div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="index.php?route=workflow-requirements/matrix<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>" class="btn btn-sm btn-outline-secondary <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-diagram-3 me-1"></i><?= h(__t('workflow_requirement_matrix')) ?>
          </a>
          <a href="index.php?route=workflow-requirements/list<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i><?= h(__t('workflow_requirements')) ?>
          </a>
          <a href="index.php?route=workflow-requirements/form<?= !empty($filters['workflowProjectID']) ? '&workflowProjectID=' . (int)$filters['workflowProjectID'] : '' ?>&returnTo=<?= $workflowRequirementSummaryReturnParam ?>" class="btn btn-sm btn-primary <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_requirement_create')) ?>
          </a>
        </div>
      </div>

      <form method="get" action="index.php" class="row g-2 align-items-end mb-4">
        <input type="hidden" name="route" value="workflow-requirements/summary">
        <div class="col-12 col-lg-3">
          <label class="form-label" for="workflowRequirementSummaryProject"><?= h(__t('workflow_project_project')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryProject" name="workflowProjectID">
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
          <label class="form-label" for="workflowRequirementSummaryDeliveryClass"><?= h(__t('workflow_requirement_delivery_class')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryDeliveryClass" name="deliveryClass">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($deliveryClassOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['deliveryClass'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementSummaryType"><?= h(__t('workflow_requirement_type')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryType" name="type">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($typeOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['type'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementSummaryPriority"><?= h(__t('workflow_requirement_priority')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryPriority" name="priority">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($priorityOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['priority'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementSummaryStatus"><?= h(__t('status')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryStatus" name="status">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($statusOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementSummaryLevel"><?= h(__t('workflow_requirement_level')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryLevel" name="requirementLevel">
            <option value=""><?= h(__t('all')) ?></option>
            <?php foreach ($requirementLevelOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['requirementLevel'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="workflowRequirementSummaryActive"><?= h(__t('workflow_project_active_filter')) ?></label>
          <select class="form-select" id="workflowRequirementSummaryActive" name="active">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>><?= h(__t('workflow_requirement_all_requirements')) ?></option>
            <option value="1" <?= (($filters['active'] ?? '') === '1') ? 'selected' : '' ?>><?= h(__t('workflow_project_active_only')) ?></option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>><?= h(__t('workflow_project_inactive_only')) ?></option>
          </select>
        </div>
        <div class="col-6 col-lg-1 d-grid">
          <button type="submit" class="btn btn-sm btn-outline-primary"><?= h(__t('filter')) ?></button>
        </div>
      </form>

      <div class="row g-3 mb-4">
        <?php foreach ($metricPanels as $panel): ?>
          <div class="col-6 col-xl-2">
            <div class="border rounded-2 p-3 h-100">
              <div class="text-muted small"><?= h((string)$panel['label']) ?></div>
              <div class="d-flex justify-content-between align-items-end mt-2">
                <div class="fs-3 fw-semibold"><?= (int)$panel['value'] ?></div>
                <span class="badge <?= h((string)$panel['class']) ?>">&nbsp;</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($parentCoverage !== []): ?>
        <div class="border rounded-2 p-3 mb-4">
          <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
              <div class="fw-semibold"><?= h(__t('workflow_requirement_parent_coverage')) ?></div>
              <div class="text-muted small"><?= h(__t('workflow_requirement_parent_coverage_help')) ?></div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <span class="badge text-bg-light border text-dark">
                <?= (int)($parentCoverageSummary['totalParents'] ?? 0) ?> <?= h(__t('workflow_requirement_level_high_level')) ?>
              </span>
              <span class="badge <?= (int)($parentCoverageSummary['parentsWithGaps'] ?? 0) > 0 ? 'text-bg-warning' : 'text-bg-success' ?>">
                <?= (int)($parentCoverageSummary['parentsWithGaps'] ?? 0) ?> <?= h(__t('workflow_requirement_needs_attention')) ?>
              </span>
              <span class="badge text-bg-light border text-dark">
                <?= (int)($parentCoverageSummary['detailRowsCovered'] ?? 0) ?> <?= h(__t('workflow_requirement_child_requirements')) ?>
              </span>
            </div>
          </div>
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th><?= h(__t('workflow_requirement')) ?></th>
                  <th><?= h(__t('workflow_project_project')) ?></th>
                  <th><?= h(__t('status')) ?></th>
                  <th><?= h(__t('workflow_requirement_detail_coverage')) ?></th>
                  <th><?= h(__t('workflow_requirement_needs_attention')) ?></th>
                  <th class="text-end"><?= h(__t('actions')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (array_slice($parentCoverage, 0, 12) as $coverageRow): ?>
                  <?php
                    $parentID = (int)($coverageRow['WorkflowRequirementID'] ?? 0);
                    $detailCount = (int)($coverageRow['DetailedCount'] ?? 0);
                    $approvedDetailCount = (int)($coverageRow['DetailedApprovedCount'] ?? 0);
                    $coveragePercent = max(0, min(100, (int)($coverageRow['CoveragePercent'] ?? 0)));
                    $attentionCount = (int)($coverageRow['AttentionCount'] ?? 0);
                    $statusCode = strtoupper((string)($coverageRow['RequirementStatusCode'] ?? ''));
                    $projectName = trim((string)($coverageRow['ProjectName'] ?? ''));
                    $requirementCode = trim((string)($coverageRow['RequirementCode'] ?? ''));
                    $requirementTitle = trim((string)($coverageRow['RequirementTitle'] ?? ''));
                    $gapBadges = [];
                    if ($detailCount <= 0) {
                        $gapBadges[] = ['class' => 'text-bg-danger', 'label' => __t('workflow_requirement_no_detailed_requirements')];
                    }
                    if ((int)($coverageRow['DetailedMissingAcceptanceCount'] ?? 0) > 0) {
                        $gapBadges[] = ['class' => 'text-bg-warning', 'label' => __t('workflow_requirement_missing_detail_acceptance') . ': ' . (int)$coverageRow['DetailedMissingAcceptanceCount']];
                    }
                    if ((int)($coverageRow['DetailedMissingOwnerCount'] ?? 0) > 0) {
                        $gapBadges[] = ['class' => 'text-bg-warning', 'label' => __t('workflow_requirement_missing_detail_owner') . ': ' . (int)$coverageRow['DetailedMissingOwnerCount']];
                    }
                    if ((int)($coverageRow['ParentMissingAcceptance'] ?? 0) > 0) {
                        $gapBadges[] = ['class' => 'text-bg-secondary', 'label' => __t('workflow_requirement_missing_acceptance')];
                    }
                    if ((int)($coverageRow['ParentMissingOwner'] ?? 0) > 0) {
                        $gapBadges[] = ['class' => 'text-bg-secondary', 'label' => __t('workflow_requirement_missing_owner')];
                    }
                  ?>
                  <tr>
                    <td>
                      <?php if ($parentID > 0): ?>
                        <a href="index.php?route=workflow-requirements/form&id=<?= $parentID ?>&returnTo=<?= $workflowRequirementSummaryReturnParam ?>" class="fw-semibold">
                          <?= h($requirementTitle !== '' ? $requirementTitle : __t('workflow_requirement')) ?>
                        </a>
                      <?php else: ?>
                        <span class="fw-semibold"><?= h($requirementTitle !== '' ? $requirementTitle : __t('workflow_requirement')) ?></span>
                      <?php endif; ?>
                      <div class="text-muted small"><?= h($requirementCode !== '' ? $requirementCode : __t('workflow_requirement_no_code')) ?></div>
                    </td>
                    <td class="small"><?= h($projectName !== '' ? $projectName : __t('workflow_project_no_project')) ?></td>
                    <td class="small"><?= h($statusCode !== '' ? wf_requirement_summary_label($statusOptions, $statusCode) : '-') ?></td>
                    <td style="min-width: 170px;">
                      <div class="d-flex justify-content-between small">
                        <span><?= $approvedDetailCount ?> / <?= $detailCount ?> <?= h(__t('workflow_requirement_approved_count')) ?></span>
                        <span><?= $coveragePercent ?>%</span>
                      </div>
                      <div class="progress" style="height: 6px;">
                        <div class="progress-bar <?= $attentionCount > 0 ? 'bg-warning' : 'bg-success' ?>" role="progressbar" style="width: <?= $coveragePercent ?>%;" aria-valuenow="<?= $coveragePercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                    </td>
                    <td>
                      <?php if ($gapBadges === []): ?>
                        <span class="badge text-bg-success"><?= h(__t('workflow_requirement_ready')) ?></span>
                      <?php else: ?>
                        <div class="d-flex gap-1 flex-wrap">
                          <?php foreach ($gapBadges as $badge): ?>
                            <span class="badge <?= h((string)$badge['class']) ?>"><?= h((string)$badge['label']) ?></span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <?php if ($parentID > 0): ?>
                        <a href="index.php?route=workflow-requirements/form&id=<?= $parentID ?>&returnTo=<?= $workflowRequirementSummaryReturnParam ?>" class="btn btn-sm btn-outline-primary" title="<?= h(__t('open')) ?>">
                          <i class="bi bi-box-arrow-up-right"></i><span class="visually-hidden"><?= h(__t('open')) ?></span>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-12 col-xl">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_by_status')) ?></div>
            <?php if ($byStatus === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_none_found')) ?></p>
            <?php else: ?>
              <?php foreach ($byStatus as $code => $count): ?>
                <div class="d-flex justify-content-between border-bottom py-1">
                  <span><?= h(wf_requirement_summary_label($statusOptions, (string)$code)) ?></span>
                  <strong><?= (int)$count ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_by_level')) ?></div>
            <?php if ($byLevel === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_none_found')) ?></p>
            <?php else: ?>
              <?php foreach ($byLevel as $code => $count): ?>
                <div class="d-flex justify-content-between border-bottom py-1">
                  <span><?= h(wf_requirement_summary_label($requirementLevelOptions, (string)$code)) ?></span>
                  <strong><?= (int)$count ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_by_priority')) ?></div>
            <?php if ($byPriority === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_none_found')) ?></p>
            <?php else: ?>
              <?php foreach ($byPriority as $code => $count): ?>
                <div class="d-flex justify-content-between border-bottom py-1">
                  <span><?= h(wf_requirement_summary_label($priorityOptions, (string)$code)) ?></span>
                  <strong><?= (int)$count ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_by_type')) ?></div>
            <?php if ($byType === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_none_found')) ?></p>
            <?php else: ?>
              <?php foreach ($byType as $code => $count): ?>
                <div class="d-flex justify-content-between border-bottom py-1">
                  <span><?= h(wf_requirement_summary_label($typeOptions, (string)$code)) ?></span>
                  <strong><?= (int)$count ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_by_delivery_class')) ?></div>
            <?php if ($byDeliveryClass === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_none_found')) ?></p>
            <?php else: ?>
              <?php foreach ($byDeliveryClass as $code => $count): ?>
                <div class="d-flex justify-content-between border-bottom py-1">
                  <span><?= h(wf_requirement_summary_label($deliveryClassOptions, (string)$code)) ?></span>
                  <strong><?= (int)$count ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-xl-5">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_by_project')) ?></div>
            <?php if ($byProject === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_none_found')) ?></p>
            <?php else: ?>
              <?php foreach (array_slice($byProject, 0, 10, true) as $projectName => $count): ?>
                <div class="d-flex justify-content-between border-bottom py-1">
                  <span><?= h((string)$projectName === 'workflow_project_no_project' ? __t('workflow_project_no_project') : (string)$projectName) ?></span>
                  <strong><?= (int)$count ?></strong>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl-7">
          <div class="border rounded-2 p-3 h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_recent')) ?></div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th><?= h(__t('workflow_requirement')) ?></th>
                    <th><?= h(__t('status')) ?></th>
                    <th><?= h(__t('updated')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($recent === []): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3"><?= h(__t('workflow_requirement_none_found')) ?></td></tr>
                  <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                      <?php
                        $id = (int)($row['WorkflowRequirementID'] ?? 0);
                        $statusCode = strtoupper((string)($row['RequirementStatusCode'] ?? ''));
                        $levelCode = strtoupper((string)($row['RequirementLevelCode'] ?? 'HIGH_LEVEL'));
                        $parentRequirementID = (int)($row['ParentRequirementID'] ?? 0);
                        $parentRequirementCode = trim((string)($row['ParentRequirementCode'] ?? ''));
                        $parentRequirementTitle = trim((string)($row['ParentRequirementTitle'] ?? ''));
                        $updatedAt = wf_requirement_summary_datetime($row['UpdatedAt'] ?? $row['CreatedAt'] ?? '');
                      ?>
                      <tr>
                        <td>
                          <a href="index.php?route=workflow-requirements/form&id=<?= $id ?>&returnTo=<?= $workflowRequirementSummaryReturnParam ?>" class="fw-semibold"><?= h((string)($row['RequirementTitle'] ?? '')) ?></a>
                          <div class="text-muted small">
                            <?= h((string)($row['RequirementCode'] ?? __t('workflow_requirement_no_code'))) ?>
                            <span class="mx-1">|</span><?= h(wf_requirement_summary_label($requirementLevelOptions, $levelCode)) ?>
                          </div>
                          <?php if ($parentRequirementID > 0): ?>
                            <div class="text-muted small">
                              <?= h(__t('workflow_requirement_parent')) ?>:
                              <a href="index.php?route=workflow-requirements/form&id=<?= $parentRequirementID ?>&returnTo=<?= $workflowRequirementSummaryReturnParam ?>">
                                <?= h($parentRequirementCode !== '' ? $parentRequirementCode : $parentRequirementTitle) ?>
                              </a>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td><?= h(wf_requirement_summary_label($statusOptions, $statusCode)) ?></td>
                        <td class="small text-muted"><?= h($updatedAt !== '' ? $updatedAt : '-') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
