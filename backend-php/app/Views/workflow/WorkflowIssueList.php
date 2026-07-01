<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$severityOptions = is_array($severityOptions ?? null) ? $severityOptions : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$tableInstalled = !empty($tableInstalled);
$canCreateIssue = !empty($canCreateIssue);
$canEditIssue = !empty($canEditIssue);
$canDeleteIssue = !empty($canDeleteIssue);
$canCreateWorkflowTask = !empty($canCreateWorkflowTask);
$currentUserId = (int)($currentUserId ?? 0);

$screenHeader = [
    'title' => __t('workflow_issues'),
    'icon' => 'bi-exclamation-triangle',
];
$returnTo = 'index.php?route=workflow-issues/list';
$queryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
if ($queryString !== '') {
    $returnTo = 'index.php?' . $queryString;
}
$returnParam = rawurlencode($returnTo);
$issueExportUrl = 'index.php?' . http_build_query(array_merge($_GET, ['route' => 'workflow-issues/export-excel']));

$label = static function (?string $code, array $options): string {
    $code = strtoupper(trim((string)$code));
    $key = $options[$code] ?? '';
    return $key !== '' ? __t($key) : $code;
};
$openCount = 0;
foreach ($rows as $row) {
    if (!in_array(strtoupper((string)($row['IssueStatusCode'] ?? '')), ['RESOLVED', 'CLOSED', 'DEFERRED'], true)) {
        $openCount++;
    }
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          <?= h(__t('workflow_issue_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <div class="text-muted small"><?= h(__t('workflow_issue_register_help')) ?></div>
          <div class="d-flex flex-wrap gap-3 mt-2">
            <span><strong><?= count($rows) ?></strong> <?= h(__t('workflow_issues')) ?></span>
            <span><strong><?= $openCount ?></strong> <?= h(__t('workflow_issue_open_count')) ?></span>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <button type="button" id="workflow-issues-print-btn" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i><?= h(__t('print')) ?>
          </button>
          <a id="workflow-issues-export-excel-btn" href="<?= h($issueExportUrl) ?>" class="btn btn-sm btn-outline-success <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-file-earmark-excel me-1"></i><?= h(__t('export_excel')) ?>
          </a>
          <?php if ($canCreateIssue): ?>
            <a id="workflow-issues-create-btn" href="index.php?route=workflow-issues/form&returnTo=<?= $returnParam ?>" class="btn btn-sm btn-primary <?= !$tableInstalled ? 'disabled' : '' ?>">
              <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_issue_create')) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <?php require __DIR__ . '/_SelectedProjectCue.php'; ?>

      <form method="get" action="index.php" id="workflow-issues-filter-form" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="route" value="workflow-issues/list">
        <div class="col-12 col-md-4">
          <label class="form-label" for="WorkflowIssueSearch"><?= h(__t('search')) ?></label>
          <input class="form-control" type="text" id="WorkflowIssueSearch" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="<?= h(__t('workflow_issue_search_placeholder')) ?>">
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label" for="WorkflowIssueProject"><?= h(__t('workflow_project_project')) ?></label>
          <select class="form-select" id="WorkflowIssueProject" name="workflowProjectID">
            <option value=""><?= h(__t('workflow_requirement_all_projects')) ?></option>
            <?php foreach ($workflowProjects as $project): ?>
              <?php $projectId = (int)($project['WorkflowProjectID'] ?? 0); ?>
              <option value="<?= $projectId ?>" <?= (int)($filters['workflowProjectID'] ?? 0) === $projectId ? 'selected' : '' ?>><?= h((string)($project['ProjectName'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label" for="WorkflowIssueStatus"><?= h(__t('status')) ?></label>
          <select class="form-select" id="WorkflowIssueStatus" name="status">
            <option value=""><?= h(__t('workflow_issue_all_statuses')) ?></option>
            <?php foreach ($statusOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label" for="WorkflowIssueSeverity"><?= h(__t('workflow_issue_severity')) ?></label>
          <select class="form-select" id="WorkflowIssueSeverity" name="severity">
            <option value=""><?= h(__t('workflow_issue_all_severities')) ?></option>
            <?php foreach ($severityOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['severity'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-1 d-grid">
          <button type="submit" id="workflow-issues-filter-btn" class="btn btn-sm btn-outline-primary"><?= h(__t('filter')) ?></button>
        </div>
        <div class="col-6 col-md-1 d-grid">
          <a id="workflow-issues-reset-btn" class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-issues/list"><?= h(__t('reset')) ?></a>
        </div>
      </form>

      <div class="table-responsive">
        <table id="workflow-issues-table" class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= h(__t('workflow_issue')) ?></th>
              <th><?= h(__t('workflow_project_project')) ?></th>
              <th><?= h(__t('workflow_issue_severity')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th><?= h(__t('workflow_issue_owner')) ?></th>
              <th><?= h(__t('workflow_issue_due_date')) ?></th>
              <th class="text-end"><?= h(__t('workflow_project_tasks')) ?></th>
              <th class="text-end"><?= h(__t('actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3"><?= h(__t('workflow_issue_none_found')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $issueId = (int)($row['WorkflowIssueID'] ?? 0);
                  $rowCanDeleteIssue = $canDeleteIssue || ($currentUserId > 0 && (int)($row['CreatedBy'] ?? 0) === $currentUserId);
                  $isInactive = (int)($row['Active'] ?? 0) !== 1;
                ?>
                <tr>
                  <td>
                    <a class="fw-semibold" href="index.php?route=workflow-issues/form&id=<?= $issueId ?>&returnTo=<?= $returnParam ?>"><?= h((string)($row['IssueTitle'] ?? '')) ?></a>
                    <div class="text-muted small"><?= h((string)($row['IssueCode'] ?? '')) ?></div>
                    <?php if (!empty($row['RequirementTitle'])): ?>
                      <div class="small"><?= h(__t('workflow_requirement')) ?>: <?= h((string)($row['RequirementCode'] ?? $row['RequirementTitle'])) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)($row['ProjectName'] ?? '')) ?></td>
                  <td><span class="badge text-bg-warning"><?= h($label((string)($row['SeverityCode'] ?? ''), $severityOptions)) ?></span></td>
                  <td><span class="badge text-bg-primary"><?= h($label((string)($row['IssueStatusCode'] ?? ''), $statusOptions)) ?></span></td>
                  <td><?= h((string)($row['OwnerName'] ?? '')) ?></td>
                  <td><?= h((string)($row['DueDate'] ?? '')) ?></td>
                  <td class="text-end">
                    <?= (int)($row['TaskCount'] ?? 0) ?>
                    <span class="text-muted small">(<?= (int)($row['OpenTaskCount'] ?? 0) ?> <?= h(__t('workflow_task_open')) ?>)</span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex justify-content-end align-items-center gap-1">
                      <?php if ($canCreateWorkflowTask && (int)($row['WorkflowProjectID'] ?? 0) > 0): ?>
                        <a class="btn btn-sm btn-outline-success" href="index.php?route=workflow-issues/form&id=<?= $issueId ?>&returnTo=<?= $returnParam ?>#issue-task-create">
                          <i class="bi bi-plus-lg me-1"></i><?= h(__t('workflow_project_task')) ?>
                        </a>
                      <?php endif; ?>
                      <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow-issues/form&id=<?= $issueId ?>&returnTo=<?= $returnParam ?>"><?= h(__t($canEditIssue ? 'edit' : 'open')) ?></a>
                      <?php if ($rowCanDeleteIssue && !$isInactive): ?>
                        <form method="post"
                              action="index.php?route=workflow-issues/delete"
                              class="d-inline"
                              data-confirm-message="<?= h(__t('workflow_issue_delete_confirm')) ?>"
                              data-confirm-button="<?= h(__t('delete')) ?>"
                              data-confirm-button-class="btn-danger">
                          <?= csrf_field() ?>
                          <input type="hidden" name="WorkflowIssueID" value="<?= $issueId ?>">
                          <input type="hidden" name="returnTo" value="<?= h($returnTo) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      <?php endif; ?>
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
