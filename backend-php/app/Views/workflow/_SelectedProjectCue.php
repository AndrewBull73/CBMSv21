<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$workflowSelectedProjectID = isset($workflowSelectedProjectID)
    ? (int)$workflowSelectedProjectID
    : (isset($workflowProjectID) ? (int)$workflowProjectID : (int)($filters['workflowProjectID'] ?? 0));

if ($workflowSelectedProjectID > 0) {
    $workflowSelectedProjectName = trim((string)($workflowSelectedProjectName ?? ''));
    $workflowSelectedProjectCode = trim((string)($workflowSelectedProjectCode ?? ''));
    $projectRows = is_array($workflowProjects ?? null) ? $workflowProjects : [];
    foreach ($projectRows as $project) {
        if ((int)($project['WorkflowProjectID'] ?? 0) !== $workflowSelectedProjectID) {
            continue;
        }
        if ($workflowSelectedProjectName === '') {
            $workflowSelectedProjectName = trim((string)($project['ProjectName'] ?? ''));
        }
        if ($workflowSelectedProjectCode === '') {
            $workflowSelectedProjectCode = trim((string)($project['ProjectCode'] ?? ''));
        }
        break;
    }

    $workflowSelectedProjectLabel = trim(($workflowSelectedProjectCode !== '' ? $workflowSelectedProjectCode . ' - ' : '') . $workflowSelectedProjectName);
    if ($workflowSelectedProjectLabel === '') {
        $workflowSelectedProjectLabel = __t('workflow_project_number', ['id' => (string)$workflowSelectedProjectID]);
    }

    $clearProjectParams = $_GET;
    $clearProjectParams['workflowProjectID'] = '';
    unset($clearProjectParams['page']);
    $workflowSelectedProjectClearUrl = 'index.php?' . http_build_query($clearProjectParams, '', '&', PHP_QUERY_RFC3986);
    ?>
    <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 mb-3">
      <div class="small">
        <span class="text-muted"><?= h(__t('workflow_project_current_context')) ?>:</span>
        <a class="fw-semibold text-decoration-none" href="index.php?route=workflow-projects/summary&id=<?= $workflowSelectedProjectID ?>">
          <?= h($workflowSelectedProjectLabel) ?>
        </a>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="<?= h($workflowSelectedProjectClearUrl) ?>">
        <i class="bi bi-x-circle me-1"></i><?= h(__t('workflow_project_clear_context')) ?>
      </a>
    </div>
<?php } ?>
