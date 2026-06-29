<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('wf_project_summary_date')) {
    function wf_project_summary_date($value): ?DateTimeImmutable
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        return new DateTimeImmutable(date('Y-m-d', $ts));
    }
}
if (!function_exists('wf_project_summary_date_text')) {
    function wf_project_summary_date_text($value): string
    {
        $date = wf_project_summary_date($value);
        return $date ? $date->format('Y-m-d') : '';
    }
}
if (!function_exists('wf_project_summary_days_between')) {
    function wf_project_summary_days_between(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int)$from->diff($to)->format('%r%a');
    }
}
if (!function_exists('wf_project_summary_task_closed')) {
    function wf_project_summary_task_closed(array $task): bool
    {
        $statusCode = strtoupper(trim((string)($task['StatusCode'] ?? '')));
        $statusName = strtoupper(trim((string)($task['StatusName'] ?? '')));
        return trim((string)($task['CompletedAt'] ?? '')) !== ''
            || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
    }
}
if (!function_exists('wf_project_summary_sort_by_date')) {
    function wf_project_summary_sort_by_date(array &$tasks, string $field): void
    {
        usort($tasks, static function (array $a, array $b) use ($field): int {
            $aDate = wf_project_summary_date($a[$field] ?? null);
            $bDate = wf_project_summary_date($b[$field] ?? null);
            $aKey = $aDate ? $aDate->format('Ymd') : '99999999';
            $bKey = $bDate ? $bDate->format('Ymd') : '99999999';
            if ($aKey === $bKey) {
                return ((int)($a['WorkflowTaskID'] ?? 0)) <=> ((int)($b['WorkflowTaskID'] ?? 0));
            }
            return $aKey <=> $bKey;
        });
    }
}

$record = is_array($record ?? null) ? $record : [];
$projectUsers = is_array($projectUsers ?? null) ? $projectUsers : [];
$projectTasks = is_array($projectTasks ?? null) ? $projectTasks : [];
$projectLinks = is_array($projectLinks ?? null) ? $projectLinks : [];
$projectRequirements = is_array($projectRequirements ?? null) ? $projectRequirements : [];
$projectLinkSummary = is_array($projectLinkSummary ?? null) ? $projectLinkSummary : [];
$workflowLinkTypeOptions = is_array($workflowLinkTypeOptions ?? null) ? $workflowLinkTypeOptions : [];
$requirementStatusOptions = is_array($requirementStatusOptions ?? null) ? $requirementStatusOptions : [];
$requirementPriorityOptions = is_array($requirementPriorityOptions ?? null) ? $requirementPriorityOptions : [];
$workflowLinksInstalled = !empty($workflowLinksInstalled);
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$projectId = (int)($record['WorkflowProjectID'] ?? 0);
$projectName = trim((string)($record['ProjectName'] ?? ''));
$projectCode = trim((string)($record['ProjectCode'] ?? ''));
$projectStatusCode = strtoupper(trim((string)($record['ProjectStatusCode'] ?? '')));
$today = new DateTimeImmutable(date('Y-m-d'));
$soonLimit = $today->modify('+7 days');

$statusLabel = static function (?string $code) use ($statusOptions): string {
    $code = strtoupper(trim((string)$code));
    $key = $statusOptions[$code] ?? '';
    return $key !== '' ? __t($key) : $code;
};
$taskStatusLabel = static function (array $task): string {
    $name = trim((string)($task['StatusName'] ?? ''));
    $code = trim((string)($task['StatusCode'] ?? ''));
    return $name !== '' ? $name : ($code !== '' ? $code : '-');
};
$linkTypeLabel = static function (string $type) use ($workflowLinkTypeOptions): string {
    $code = strtoupper(trim($type));
    $key = (string)($workflowLinkTypeOptions[$code] ?? '');
    if ($key !== '') {
        $translated = __t($key);
        return $translated !== $key ? $translated : ucwords(strtolower(str_replace('_', ' ', $code)));
    }
    return $code !== '' ? ucwords(strtolower(str_replace('_', ' ', $code))) : __t('workflow_link_type_related_item');
};
$requirementLabel = static function (array $options, ?string $code): string {
    $code = strtoupper(trim((string)$code));
    $key = (string)($options[$code] ?? '');
    return $key !== '' ? __t($key) : ($code !== '' ? ucwords(strtolower(str_replace('_', ' ', $code))) : '-');
};
$projectNonRequirementLinks = array_values(array_filter($projectLinks, static function (array $link): bool {
    return strtoupper(trim((string)($link['LinkTypeCode'] ?? ''))) !== 'REQUIREMENT';
}));

$totalTasks = count($projectTasks);
$completedTasks = 0;
$openTasks = 0;
$overdueTasks = [];
$dueSoonTasks = [];
$upcomingTasks = [];
$unscheduledTasks = [];
$percentTotal = 0.0;
$ganttRows = [];
$timelineStart = wf_project_summary_date($record['StartDate'] ?? null);
$timelineEnd = wf_project_summary_date($record['TargetEndDate'] ?? null);

foreach ($projectTasks as $task) {
    $task = (array)$task;
    $isClosed = wf_project_summary_task_closed($task);
    $percent = max(0.0, min(100.0, (float)($task['PercentComplete'] ?? 0)));
    $percentTotal += $percent;

    if ($isClosed) {
        $completedTasks++;
    } else {
        $openTasks++;
        $dueDate = wf_project_summary_date($task['DueDate'] ?? null);
        if ($dueDate && $dueDate < $today) {
            $overdueTasks[] = $task;
        } elseif ($dueDate && $dueDate <= $soonLimit) {
            $dueSoonTasks[] = $task;
            $upcomingTasks[] = $task;
        } elseif ($dueDate) {
            $upcomingTasks[] = $task;
        }
    }

    $taskStart = wf_project_summary_date($task['PlannedStartDate'] ?? null)
        ?? wf_project_summary_date($task['DueDate'] ?? null);
    $taskEnd = wf_project_summary_date($task['PlannedEndDate'] ?? null)
        ?? wf_project_summary_date($task['DueDate'] ?? null)
        ?? $taskStart;

    if (!$taskStart || !$taskEnd) {
        $unscheduledTasks[] = $task;
        continue;
    }
    if ($taskEnd < $taskStart) {
        $taskEnd = $taskStart;
    }
    if (!$timelineStart || $taskStart < $timelineStart) {
        $timelineStart = $taskStart;
    }
    if (!$timelineEnd || $taskEnd > $timelineEnd) {
        $timelineEnd = $taskEnd;
    }
    $ganttRows[] = [
        'task' => $task,
        'start' => $taskStart,
        'end' => $taskEnd,
        'percent' => $percent,
        'closed' => $isClosed,
    ];
}

wf_project_summary_sort_by_date($overdueTasks, 'DueDate');
wf_project_summary_sort_by_date($dueSoonTasks, 'DueDate');
wf_project_summary_sort_by_date($upcomingTasks, 'DueDate');

$completionPercent = $totalTasks > 0 ? (int)round(($completedTasks / $totalTasks) * 100) : 0;
$averageProgress = $totalTasks > 0 ? (int)round($percentTotal / $totalTasks) : 0;
$unscheduledCount = count($unscheduledTasks);
$linkedRequirements = count($projectRequirements);
$linkedTraining = (int)($projectLinkSummary['training'] ?? 0);
$linkedTesting = (int)($projectLinkSummary['testing'] ?? 0);
$linkedDefects = (int)($projectLinkSummary['defects'] ?? 0);
$linkedRelease = (int)($projectLinkSummary['release'] ?? 0);
$linkedDocumentation = (int)($projectLinkSummary['documentation'] ?? 0);
$linkedOther = (int)($projectLinkSummary['other'] ?? 0);
$linkedTotal = $linkedRequirements + $linkedTraining + $linkedTesting + $linkedDefects + $linkedRelease + $linkedDocumentation + $linkedOther;

$projectStart = wf_project_summary_date($record['StartDate'] ?? null);
$projectEnd = wf_project_summary_date($record['TargetEndDate'] ?? null);
$dateProgress = 0;
if ($projectStart && $projectEnd && $projectEnd >= $projectStart) {
    $projectDays = max(1, wf_project_summary_days_between($projectStart, $projectEnd) + 1);
    $elapsedDays = max(0, min($projectDays, wf_project_summary_days_between($projectStart, $today) + 1));
    $dateProgress = (int)round(($elapsedDays / $projectDays) * 100);
}

$healthKey = 'workflow_project_health_no_tasks';
$healthClass = 'secondary';
if ($totalTasks > 0 && $openTasks === 0) {
    $healthKey = 'workflow_project_health_complete';
    $healthClass = 'success';
} elseif ($overdueTasks !== [] || ($projectEnd && $projectEnd < $today && $openTasks > 0)) {
    $healthKey = 'workflow_project_health_at_risk';
    $healthClass = 'danger';
} elseif ($dueSoonTasks !== []) {
    $healthKey = 'workflow_project_health_watch';
    $healthClass = 'warning';
} elseif ($totalTasks > 0) {
    $healthKey = 'workflow_project_health_on_track';
    $healthClass = 'primary';
}

if ($timelineStart && $timelineEnd && $timelineEnd < $timelineStart) {
    $timelineEnd = $timelineStart;
}
$timelineDays = ($timelineStart && $timelineEnd)
    ? max(1, wf_project_summary_days_between($timelineStart, $timelineEnd) + 1)
    : 1;
$todayOffsetPercent = null;
if ($timelineStart && $timelineEnd && $today >= $timelineStart && $today <= $timelineEnd) {
    $todayOffsetPercent = max(0, min(100, (wf_project_summary_days_between($timelineStart, $today) / $timelineDays) * 100));
}

$screenHeader = [
    'title' => __t('workflow_project_summary'),
    'icon' => 'bi-kanban',
];
$backUrl = trim((string)($backUrl ?? ''));
if ($backUrl === '') {
    $backUrl = 'index.php?route=workflow-projects/list';
}
$workflowProjectSummaryReturnTo = 'index.php?route=workflow-projects/summary';
$workflowProjectSummaryQueryString = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
if ($workflowProjectSummaryQueryString !== '') {
    $workflowProjectSummaryReturnTo = 'index.php?' . $workflowProjectSummaryQueryString;
}
$workflowProjectSummaryReturnParam = rawurlencode($workflowProjectSummaryReturnTo);
?>

<style>
  .project-summary-band {
    border: 1px solid #dfe7ef;
    border-radius: .5rem;
    background: #f8fafc;
    padding: 1rem;
  }
  .project-summary-metric {
    border: 1px solid #dfe7ef;
    border-radius: .5rem;
    background: #fff;
    padding: .85rem;
    min-height: 6rem;
  }
  .project-summary-metric .metric-value {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.1;
  }
  .project-summary-gantt {
    border: 1px solid #dfe7ef;
    border-radius: .5rem;
    overflow: hidden;
    background: #fff;
  }
  .project-summary-gantt-row,
  .project-summary-gantt-header {
    display: grid;
    grid-template-columns: minmax(14rem, 22rem) minmax(24rem, 1fr);
  }
  .project-summary-gantt-header {
    background: #f7f9fc;
    border-bottom: 1px solid #dfe7ef;
    color: #526274;
    font-size: .8rem;
    font-weight: 600;
  }
  .project-summary-gantt-header > div,
  .project-summary-gantt-row > div {
    padding: .55rem .75rem;
  }
  .project-summary-gantt-row + .project-summary-gantt-row {
    border-top: 1px solid #edf1f5;
  }
  .project-summary-gantt-track {
    position: relative;
    min-height: 1.85rem;
    border-radius: .35rem;
    background:
      repeating-linear-gradient(
        to right,
        #f8fafc 0,
        #f8fafc calc(10% - 1px),
        #eef3f8 calc(10% - 1px),
        #eef3f8 10%
      );
  }
  .project-summary-gantt-bar {
    position: absolute;
    top: .35rem;
    height: 1.15rem;
    border-radius: .35rem;
    background: #376fa8;
    color: #fff;
    font-size: .72rem;
    line-height: 1.15rem;
    overflow: hidden;
    white-space: nowrap;
  }
  .project-summary-gantt-bar.is-complete {
    background: #2f8750;
  }
  .project-summary-gantt-progress {
    position: absolute;
    inset: 0 auto 0 0;
    background: rgba(255, 255, 255, .25);
  }
  .project-summary-gantt-label {
    position: relative;
    z-index: 1;
    padding: 0 .4rem;
  }
  .project-summary-today {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #d14444;
  }
  .project-summary-linked-item {
    border: 1px solid #e4ebf2;
    border-radius: .5rem;
    background: #fff;
    padding: .75rem .85rem;
  }
  .project-summary-linked-title {
    font-weight: 700;
    word-break: break-word;
  }
  .project-summary-linked-meta {
    color: #6c7a89;
    font-size: .78rem;
  }
  @media (max-width: 900px) {
    .project-summary-gantt {
      overflow-x: auto;
    }
    .project-summary-gantt-row,
    .project-summary-gantt-header {
      min-width: 46rem;
    }
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

      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
          <h1 class="h4 mb-1"><?= h($projectName !== '' ? $projectName : __t('workflow_project_project')) ?></h1>
          <div class="text-muted small">
            <?php if ($projectCode !== ''): ?><?= h($projectCode) ?> &middot; <?php endif; ?>
            <?= h(__t('workflow_project_summary_help')) ?>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= h($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
          <a href="index.php?route=workflow-projects/form&id=<?= $projectId ?>&returnTo=<?= $workflowProjectSummaryReturnParam ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil-square me-1"></i><?= h(__t('workflow_project_edit')) ?>
          </a>
          <a href="index.php?route=workflow/edit&workflowProjectID=<?= $projectId ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_project_create_task')) ?>
          </a>
          <a href="index.php?route=workflow-requirements/form&workflowProjectID=<?= $projectId ?>&returnTo=<?= $workflowProjectSummaryReturnParam ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-journal-plus me-1"></i><?= h(__t('workflow_requirement_create')) ?>
          </a>
          <a href="index.php?route=workflow/list&workflowProjectID=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-task me-1"></i><?= h(__t('workflow_project_view_tasks')) ?>
          </a>
        </div>
      </div>

      <div class="project-summary-band mb-3">
        <div class="row g-3 align-items-start">
          <div class="col-12 col-lg-8">
            <div class="d-flex flex-wrap gap-2 mb-2">
              <span class="badge text-bg-<?= h($healthClass) ?>"><?= h(__t($healthKey)) ?></span>
              <span class="badge text-bg-<?= ((int)($record['Active'] ?? 0) === 1) ? 'primary' : 'secondary' ?>"><?= h($statusLabel($projectStatusCode)) ?></span>
            </div>
            <div class="text-muted small mb-2">
              <?= h(__t('workflow_project_owner')) ?>:
              <?= h((string)($record['ProjectOwnerName'] ?? __t('workflow_project_no_owner'))) ?>
            </div>
            <div>
              <?php $description = trim((string)($record['Description'] ?? '')); ?>
              <?= h($description !== '' ? $description : __t('workflow_project_no_description')) ?>
            </div>
          </div>
          <div class="col-12 col-lg-4">
            <div class="small text-muted mb-1"><?= h(__t('workflow_project_dates')) ?></div>
            <div class="fw-semibold">
              <?= h(wf_project_summary_date_text($record['StartDate'] ?? null) ?: '-') ?>
              <span class="text-muted">-</span>
              <?= h(wf_project_summary_date_text($record['TargetEndDate'] ?? null) ?: '-') ?>
            </div>
            <?php if (trim((string)($record['ActualEndDate'] ?? '')) !== ''): ?>
              <div class="small text-muted mt-1">
                <?= h(__t('workflow_project_actual_end_date')) ?>:
                <?= h(wf_project_summary_date_text($record['ActualEndDate'])) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="project-summary-metric">
            <div class="text-muted small"><?= h(__t('workflow_project_tasks')) ?></div>
            <div class="metric-value"><?= $totalTasks ?></div>
            <div class="small text-muted"><?= $openTasks ?> <?= h(__t('workflow_task_open')) ?>, <?= $completedTasks ?> <?= h(__t('workflow_task_completed')) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="project-summary-metric">
            <div class="text-muted small"><?= h(__t('workflow_project_task_completion')) ?></div>
            <div class="metric-value"><?= $completionPercent ?>%</div>
            <div class="progress mt-2" role="progressbar" aria-valuenow="<?= $completionPercent ?>" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar bg-success" style="width: <?= $completionPercent ?>%;"></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="project-summary-metric">
            <div class="text-muted small"><?= h(__t('workflow_project_average_progress')) ?></div>
            <div class="metric-value"><?= $averageProgress ?>%</div>
            <div class="small text-muted"><?= h(__t('workflow_task_percent_complete')) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="project-summary-metric">
            <div class="text-muted small"><?= h(__t('workflow_project_date_progress')) ?></div>
            <div class="metric-value"><?= $dateProgress ?>%</div>
            <div class="small text-muted"><?= count($projectUsers) ?> <?= h(__t('workflow_project_users')) ?></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
          <h2 class="h6"><?= h(__t('workflow_project_health')) ?></h2>
          <div class="list-group list-group-flush border rounded">
            <div class="list-group-item d-flex justify-content-between">
              <span><?= h(__t('workflow_task_overdue')) ?></span>
              <strong class="<?= $overdueTasks !== [] ? 'text-danger' : '' ?>"><?= count($overdueTasks) ?></strong>
            </div>
            <div class="list-group-item d-flex justify-content-between">
              <span><?= h(__t('workflow_task_due_soon')) ?></span>
              <strong><?= count($dueSoonTasks) ?></strong>
            </div>
            <div class="list-group-item d-flex justify-content-between">
              <span><?= h(__t('workflow_project_unscheduled_tasks')) ?></span>
              <strong><?= $unscheduledCount ?></strong>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-8">
          <h2 class="h6"><?= h(__t('workflow_project_users')) ?></h2>
          <?php if ($projectUsers === []): ?>
            <div class="text-muted small border rounded p-3"><?= h(__t('workflow_project_no_users')) ?></div>
          <?php else: ?>
            <div class="d-flex flex-wrap gap-2 border rounded p-3">
              <?php foreach ($projectUsers as $member): ?>
                <span class="badge text-bg-light border">
                  <i class="bi bi-person me-1"></i><?= h((string)($member['UserName'] ?? '')) ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
          <div>
            <h2 class="h6 mb-0"><?= h(__t('workflow_project_linked_work')) ?></h2>
            <div class="text-muted small"><?= h(__t('workflow_project_linked_work_help')) ?></div>
          </div>
          <?php if ($workflowLinksInstalled): ?>
            <span class="badge text-bg-light border"><?= h(__t('workflow_project_linked_items_count', ['count' => $linkedTotal])) ?></span>
          <?php endif; ?>
        </div>

        <?php if (!$workflowLinksInstalled): ?>
          <div class="alert alert-warning py-2">
            <?= h(__t('workflow_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
          </div>
        <?php else: ?>
          <div class="row row-cols-2 row-cols-lg-5 g-3 mb-3">
            <div class="col">
              <div class="project-summary-metric">
                <div class="text-muted small"><?= h(__t('workflow_project_linked_requirements')) ?></div>
                <div class="metric-value"><?= $linkedRequirements ?></div>
              </div>
            </div>
            <div class="col">
              <div class="project-summary-metric">
                <div class="text-muted small"><?= h(__t('workflow_project_linked_training')) ?></div>
                <div class="metric-value"><?= $linkedTraining ?></div>
              </div>
            </div>
            <div class="col">
              <div class="project-summary-metric">
                <div class="text-muted small"><?= h(__t('workflow_project_linked_testing')) ?></div>
                <div class="metric-value"><?= $linkedTesting ?></div>
              </div>
            </div>
            <div class="col">
              <div class="project-summary-metric">
                <div class="text-muted small"><?= h(__t('workflow_project_linked_defects')) ?></div>
                <div class="metric-value"><?= $linkedDefects ?></div>
              </div>
            </div>
            <div class="col">
              <div class="project-summary-metric">
                <div class="text-muted small"><?= h(__t('workflow_project_linked_release')) ?></div>
                <div class="metric-value"><?= $linkedRelease ?></div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
              <div>
                <div class="fw-semibold"><?= h(__t('workflow_project_linked_requirements')) ?></div>
                <div class="text-muted small"><?= h(__t('workflow_project_high_level_requirements')) ?></div>
              </div>
              <span class="badge text-bg-light border"><?= count($projectRequirements) ?></span>
            </div>

            <?php if ($projectRequirements === []): ?>
              <div class="text-muted small border rounded p-3"><?= h(__t('workflow_requirement_none_found')) ?></div>
            <?php else: ?>
              <div class="row g-2">
                <?php foreach (array_slice($projectRequirements, 0, 8) as $requirement): ?>
                  <?php
                    $requirementId = (int)($requirement['WorkflowRequirementID'] ?? 0);
                    if ($requirementId <= 0) {
                        continue;
                    }
                    $requirementCode = trim((string)($requirement['RequirementCode'] ?? ''));
                    $requirementTitle = trim((string)($requirement['RequirementTitle'] ?? ''));
                    $requirementModule = trim((string)($requirement['ModuleCode'] ?? ''));
                    $requirementStatus = strtoupper(trim((string)($requirement['RequirementStatusCode'] ?? '')));
                    $requirementPriority = strtoupper(trim((string)($requirement['PriorityCode'] ?? '')));
                    $childRequirementCount = (int)($requirement['ChildRequirementCount'] ?? 0);
                    $requirementHeading = $requirementTitle !== '' ? $requirementTitle : ($requirementCode !== '' ? $requirementCode : __t('workflow_requirement'));
                    $requirementUrl = 'index.php?route=workflow-requirements/form&id=' . $requirementId . '&returnTo=' . $workflowProjectSummaryReturnParam;
                  ?>
                  <div class="col-12 col-lg-6">
                    <div class="project-summary-linked-item h-100">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="project-summary-linked-title">
                            <a href="<?= h($requirementUrl) ?>"><?= h($requirementHeading) ?></a>
                          </div>
                          <div class="project-summary-linked-meta">
                            <?php if ($requirementCode !== ''): ?><?= h($requirementCode) ?><?php endif; ?>
                            <?php if ($requirementModule !== ''): ?>
                              <span class="mx-1">&middot;</span><?= h($requirementModule) ?>
                            <?php endif; ?>
                          </div>
                          <div class="d-flex flex-wrap gap-1 mt-2">
                            <span class="badge text-bg-light border">
                              <?= $childRequirementCount ?> <?= h(__t('workflow_requirement_level_detailed')) ?>
                            </span>
                            <span class="badge text-bg-info"><?= h($requirementLabel($requirementStatusOptions, $requirementStatus)) ?></span>
                            <span class="badge text-bg-secondary"><?= h($requirementLabel($requirementPriorityOptions, $requirementPriority)) ?></span>
                          </div>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="<?= h($requirementUrl) ?>">
                          <?= h(__t('open')) ?>
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($projectRequirements === [] && $projectNonRequirementLinks === []): ?>
            <div class="text-muted small border rounded p-3"><?= h(__t('workflow_project_no_linked_work')) ?></div>
          <?php elseif ($projectNonRequirementLinks !== []): ?>
            <div class="row g-2">
              <?php foreach (array_slice($projectNonRequirementLinks, 0, 8) as $link): ?>
                <?php
                  $linkedTitle = trim((string)($link['LinkedTitle'] ?? ''));
                  $linkedEntity = trim((string)($link['LinkedEntity'] ?? ''));
                  $linkedKey = trim((string)($link['LinkedEntityKey'] ?? ''));
                  $linkedUrl = trim((string)($link['LinkedUrl'] ?? ''));
                  if (str_starts_with($linkedUrl, 'index.php?route=workflow-requirements/form') && strpos($linkedUrl, 'returnTo=') === false) {
                      $linkedUrl .= '&returnTo=' . $workflowProjectSummaryReturnParam;
                  }
                  $linkTaskId = (int)($link['WorkflowTaskID'] ?? 0);
                  $linkHeading = $linkedTitle !== '' ? $linkedTitle : ($linkedKey !== '' ? $linkedKey : ($linkedEntity !== '' ? $linkedEntity : __t('workflow_project_linked_work')));
                ?>
                <div class="col-12 col-lg-6">
                  <div class="project-summary-linked-item h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                      <div>
                        <div class="project-summary-linked-title">
                          <?php if ($linkedUrl !== ''): ?>
                            <a href="<?= h($linkedUrl) ?>"><?= h($linkHeading) ?></a>
                          <?php else: ?>
                            <?= h($linkHeading) ?>
                          <?php endif; ?>
                        </div>
                        <div class="project-summary-linked-meta">
                          <span class="badge text-bg-light border me-1"><?= h($linkTypeLabel((string)($link['LinkTypeCode'] ?? ''))) ?></span>
                          <?= h($linkedEntity !== '' ? $linkedEntity : '-') ?>
                          <?php if ($linkedKey !== ''): ?> &middot; <?= h($linkedKey) ?><?php endif; ?>
                        </div>
                      </div>
                      <?php if ($linkTaskId > 0): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow/edit&id=<?= $linkTaskId ?>&workflowProjectID=<?= $projectId ?>">
                          <i class="bi bi-list-task me-1"></i><?= h(__t('workflow_project_task')) ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div>
            <h2 class="h6 mb-0"><?= h(__t('workflow_project_schedule_snapshot')) ?></h2>
            <div class="text-muted small">
              <?= h($timelineStart ? $timelineStart->format('Y-m-d') : '-') ?>
              <span>-</span>
              <?= h($timelineEnd ? $timelineEnd->format('Y-m-d') : '-') ?>
            </div>
          </div>
          <a href="index.php?route=workflow-projects/form&id=<?= $projectId ?>&returnTo=<?= $workflowProjectSummaryReturnParam ?>#workflow-project-gantt" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-bar-chart-steps me-1"></i><?= h(__t('workflow_project_gantt_chart')) ?>
          </a>
        </div>

        <?php if ($ganttRows === []): ?>
          <div class="alert alert-light border"><?= h(__t('workflow_project_no_scheduled_tasks')) ?></div>
        <?php else: ?>
          <div class="project-summary-gantt">
            <div class="project-summary-gantt-header">
              <div><?= h(__t('workflow_project_tasks')) ?></div>
              <div><?= h(__t('workflow_project_timeline')) ?></div>
            </div>
            <?php foreach ($ganttRows as $row): ?>
              <?php
                $task = (array)$row['task'];
                $taskId = (int)($task['WorkflowTaskID'] ?? 0);
                $taskTitle = trim((string)($task['Title'] ?? ''));
                $barStartDate = $row['start'];
                $barEndDate = $row['end'];
                $offsetDays = $timelineStart ? max(0, wf_project_summary_days_between($timelineStart, $barStartDate)) : 0;
                $durationDays = max(1, wf_project_summary_days_between($barStartDate, $barEndDate) + 1);
                $left = max(0.0, min(100.0, ($offsetDays / $timelineDays) * 100));
                $availableWidth = max(0.5, 100.0 - $left);
                $width = min($availableWidth, max(1.8, ($durationDays / $timelineDays) * 100));
                $percent = (int)round((float)$row['percent']);
                $isComplete = !empty($row['closed']) || $percent >= 100;
                $hasParent = (int)($task['ParentWorkflowTaskID'] ?? 0) > 0;
              ?>
              <div class="project-summary-gantt-row">
                <div class="small <?= $hasParent ? 'ps-4' : '' ?>">
                  <?php if ($hasParent): ?><i class="bi bi-arrow-return-right text-muted me-1"></i><?php endif; ?>
                  <a href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $projectId ?>" class="fw-semibold">
                    <?= h($taskTitle !== '' ? $taskTitle : __t('workflow_task_number', ['id' => $taskId])) ?>
                  </a>
                  <div class="text-muted"><?= h($barStartDate->format('Y-m-d')) ?> - <?= h($barEndDate->format('Y-m-d')) ?></div>
                </div>
                <div>
                  <div class="project-summary-gantt-track">
                    <?php if ($todayOffsetPercent !== null): ?>
                      <span class="project-summary-today"
                            style="left: <?= h(number_format($todayOffsetPercent, 4, '.', '')) ?>%;"
                            title="<?= h(__t('workflow_project_today')) ?>"></span>
                    <?php endif; ?>
                    <div class="project-summary-gantt-bar <?= $isComplete ? 'is-complete' : '' ?>"
                         style="left: <?= h(number_format($left, 4, '.', '')) ?>%; width: <?= h(number_format($width, 4, '.', '')) ?>%;">
                      <span class="project-summary-gantt-progress" style="width: <?= h((string)$percent) ?>%;"></span>
                      <span class="project-summary-gantt-label"><?= h($percent) ?>%</span>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
          <h2 class="h6"><?= h(__t('workflow_project_overdue_tasks')) ?></h2>
          <?php if ($overdueTasks === []): ?>
            <div class="text-muted small border rounded p-3"><?= h(__t('workflow_project_no_overdue_tasks')) ?></div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach (array_slice($overdueTasks, 0, 6) as $task): ?>
                <?php $taskId = (int)($task['WorkflowTaskID'] ?? 0); ?>
                <a class="list-group-item list-group-item-action" href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $projectId ?>">
                  <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold"><?= h((string)($task['Title'] ?? __t('workflow_task_number', ['id' => $taskId]))) ?></span>
                    <span class="text-danger small"><?= h(wf_project_summary_date_text($task['DueDate'] ?? null)) ?></span>
                  </div>
                  <div class="text-muted small"><?= h((string)($task['AssignedToName'] ?? '-')) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="col-12 col-lg-6">
          <h2 class="h6"><?= h(__t('workflow_project_upcoming_tasks')) ?></h2>
          <?php if ($upcomingTasks === []): ?>
            <div class="text-muted small border rounded p-3"><?= h(__t('workflow_project_no_upcoming_tasks')) ?></div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach (array_slice($upcomingTasks, 0, 6) as $task): ?>
                <?php $taskId = (int)($task['WorkflowTaskID'] ?? 0); ?>
                <a class="list-group-item list-group-item-action" href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $projectId ?>">
                  <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold"><?= h((string)($task['Title'] ?? __t('workflow_task_number', ['id' => $taskId]))) ?></span>
                    <span class="small"><?= h(wf_project_summary_date_text($task['DueDate'] ?? null)) ?></span>
                  </div>
                  <div class="text-muted small"><?= h((string)($task['AssignedToName'] ?? '-')) ?></div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="h6 mb-0"><?= h(__t('workflow_project_task_overview')) ?></h2>
        <a href="index.php?route=workflow/list&workflowProjectID=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary">
          <?= h(__t('workflow_project_view_tasks')) ?>
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= h(__t('workflow_project_tasks')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th><?= h(__t('workflow_task_assigned_to')) ?></th>
              <th><?= h(__t('workflow_task_due_date')) ?></th>
              <th class="text-end"><?= h(__t('workflow_task_percent_complete')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($projectTasks === []): ?>
              <tr><td colspan="5" class="text-center text-muted py-3"><?= h(__t('workflow_project_no_tasks_summary')) ?></td></tr>
            <?php else: ?>
              <?php foreach ($projectTasks as $task): ?>
                <?php
                  $taskId = (int)($task['WorkflowTaskID'] ?? 0);
                  $taskPercent = (int)round(max(0, min(100, (float)($task['PercentComplete'] ?? 0))));
                  $hasParent = (int)($task['ParentWorkflowTaskID'] ?? 0) > 0;
                ?>
                <tr>
                  <td class="<?= $hasParent ? 'ps-4' : '' ?>">
                    <?php if ($hasParent): ?><i class="bi bi-arrow-return-right text-muted me-1"></i><?php endif; ?>
                    <a href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $projectId ?>">
                      <?= h((string)($task['Title'] ?? __t('workflow_task_number', ['id' => $taskId]))) ?>
                    </a>
                  </td>
                  <td><?= h($taskStatusLabel((array)$task)) ?></td>
                  <td><?= h((string)($task['AssignedToName'] ?? '-')) ?></td>
                  <td><?= h(wf_project_summary_date_text($task['DueDate'] ?? null) ?: '-') ?></td>
                  <td class="text-end"><?= $taskPercent ?>%</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
