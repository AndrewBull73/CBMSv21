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
$tableInstalled = !empty($tableInstalled);

$activeCount = 0;
$openTaskCount = 0;
foreach ($rows as $row) {
    if ((int)($row['Active'] ?? 0) === 1) {
        $activeCount++;
    }
    $openTaskCount += (int)($row['OpenTaskCount'] ?? 0);
}

$screenHeader = [
    'title' => __t('workflow_projects'),
    'icon' => 'bi-kanban',
];
$workflowProjectListReturnTo = 'index.php?route=workflow-projects/list';
$workflowProjectListQueryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
if ($workflowProjectListQueryString !== '') {
    $workflowProjectListReturnTo = 'index.php?' . $workflowProjectListQueryString;
}
$workflowProjectListReturnParam = rawurlencode($workflowProjectListReturnTo);

$statusLabel = static function (?string $code) use ($statusOptions): string {
    $code = strtoupper(trim((string)$code));
    $key = $statusOptions[$code] ?? '';
    return $key !== '' ? __t($key) : $code;
};
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
          <?= h(__t('workflow_project_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <div class="text-muted small"><?= h(__t('workflow_project_register_help')) ?></div>
          <div class="d-flex flex-wrap gap-3 mt-2">
            <span><strong><?= count($rows) ?></strong> <?= h(__t('workflow_projects')) ?></span>
            <span><strong><?= $activeCount ?></strong> <?= h(__t('workflow_project_active_projects')) ?></span>
            <span><strong><?= $openTaskCount ?></strong> <?= h(__t('workflow_project_open_tasks')) ?></span>
          </div>
        </div>
        <a href="index.php?route=workflow-projects/form&returnTo=<?= $workflowProjectListReturnParam ?>" class="btn btn-sm btn-primary <?= !$tableInstalled ? 'disabled' : '' ?>">
          <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_project_create')) ?>
        </a>
      </div>

      <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="route" value="workflow-projects/list">
        <div class="col-12 col-md-5">
          <label class="form-label" for="workflowProjectSearch"><?= h(__t('search')) ?></label>
          <input class="form-control" type="text" id="workflowProjectSearch" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="<?= h(__t('workflow_project_search_placeholder')) ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label" for="workflowProjectStatus"><?= h(__t('status')) ?></label>
          <select class="form-select" id="workflowProjectStatus" name="status">
            <option value=""><?= h(__t('workflow_project_all_statuses')) ?></option>
            <?php foreach ($statusOptions as $code => $labelKey): ?>
              <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($filters['status'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label" for="workflowProjectActive"><?= h(__t('workflow_project_active_filter')) ?></label>
          <select class="form-select" id="workflowProjectActive" name="active">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>><?= h(__t('workflow_project_all_projects')) ?></option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>><?= h(__t('workflow_project_active_only')) ?></option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>><?= h(__t('workflow_project_inactive_only')) ?></option>
          </select>
        </div>
        <div class="col-6 col-md-1 d-grid">
          <button type="submit" class="btn btn-sm btn-outline-primary"><?= h(__t('filter')) ?></button>
        </div>
        <div class="col-6 col-md-1 d-grid">
          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-projects/list"><?= h(__t('reset')) ?></a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= h(__t('workflow_project_project')) ?></th>
              <th><?= h(__t('workflow_project_owner')) ?></th>
              <th><?= h(__t('workflow_project_users')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th><?= h(__t('workflow_project_dates')) ?></th>
              <th class="text-end"><?= h(__t('workflow_project_tasks')) ?></th>
              <th class="text-end"><?= h(__t('actions')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-3"><?= h(__t('workflow_project_none_found')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $projectId = (int)($row['WorkflowProjectID'] ?? 0);
                  $statusCode = strtoupper(trim((string)($row['ProjectStatusCode'] ?? '')));
                  $projectUserCount = (int)($row['ProjectUserCount'] ?? 0);
                  $projectUserOverflow = (int)($row['ProjectUserNamesOverflow'] ?? 0);
                ?>
                <tr>
                  <td>
                    <a class="fw-semibold" href="index.php?route=workflow-projects/summary&id=<?= $projectId ?>&returnTo=<?= $workflowProjectListReturnParam ?>">
                      <?= h((string)($row['ProjectName'] ?? '')) ?>
                    </a>
                    <?php if (!empty($row['ProjectCode'])): ?>
                      <div class="text-muted small"><?= h((string)$row['ProjectCode']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= h((string)($row['ProjectOwnerName'] ?? '')) ?></td>
                  <td class="small">
                    <?php if ($projectUserCount > 0): ?>
                      <div><?= h((string)($row['ProjectUserNames'] ?? '')) ?></div>
                      <div class="text-muted">
                        <?= $projectUserCount ?> <?= h(__t('workflow_project_users')) ?>
                        <?php if ($projectUserOverflow > 0): ?>
                          <span>(+<?= $projectUserOverflow ?>)</span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted"><?= h(__t('workflow_project_no_users')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= ((int)($row['Active'] ?? 0) === 1) ? 'primary' : 'secondary' ?>">
                      <?= h($statusLabel($statusCode)) ?>
                    </span>
                    <?php if ((int)($row['Active'] ?? 0) !== 1): ?>
                      <div class="text-muted small"><?= h(__t('workflow_project_inactive')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="small">
                    <?= h((string)($row['StartDate'] ?? '')) ?>
                    <?php if (!empty($row['TargetEndDate'])): ?>
                      <span class="text-muted">-</span> <?= h((string)$row['TargetEndDate']) ?>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?= (int)($row['TaskCount'] ?? 0) ?>
                    <span class="text-muted small">(<?= (int)($row['OpenTaskCount'] ?? 0) ?> <?= h(__t('workflow_task_open')) ?>)</span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex justify-content-end align-items-center gap-1">
                      <a class="btn btn-sm btn-outline-success" href="index.php?route=workflow/edit&workflowProjectID=<?= $projectId ?>">
                        <i class="bi bi-plus-lg me-1"></i><?= h(__t('workflow_project_task')) ?>
                      </a>
                      <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary" type="button" id="workflowProjectActions<?= $projectId ?>" data-bs-toggle="dropdown" aria-expanded="false" title="<?= h(__t('actions')) ?>" aria-label="<?= h(__t('actions')) ?>">
                          <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="workflowProjectActions<?= $projectId ?>">
                          <li>
                            <a class="dropdown-item" href="index.php?route=workflow-projects/summary&id=<?= $projectId ?>&returnTo=<?= $workflowProjectListReturnParam ?>">
                              <i class="bi bi-kanban me-2"></i><?= h(__t('workflow_project_summary')) ?>
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item" href="index.php?route=workflow-projects/form&id=<?= $projectId ?>&returnTo=<?= $workflowProjectListReturnParam ?>">
                              <i class="bi bi-pencil-square me-2"></i><?= h(__t('workflow_project_edit')) ?>
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item" href="index.php?route=workflow-requirements/form&workflowProjectID=<?= $projectId ?>&returnTo=<?= $workflowProjectListReturnParam ?>">
                              <i class="bi bi-journal-plus me-2"></i><?= h(__t('workflow_requirement_create')) ?>
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item" href="index.php?route=workflow/list&workflowProjectID=<?= $projectId ?>">
                              <i class="bi bi-list-task me-2"></i><?= h(__t('workflow_project_view_tasks')) ?>
                            </a>
                          </li>
                          <li>
                            <a class="dropdown-item" href="index.php?route=workflow-projects/form&id=<?= $projectId ?>&returnTo=<?= $workflowProjectListReturnParam ?>#workflow-project-gantt">
                              <i class="bi bi-bar-chart-steps me-2"></i><?= h(__t('workflow_project_gantt_chart')) ?>
                            </a>
                          </li>
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
