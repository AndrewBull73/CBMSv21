<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('wf_project_date')) {
    function wf_project_date($value): ?DateTimeImmutable
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
if (!function_exists('wf_project_days_between')) {
    function wf_project_days_between(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int)$from->diff($to)->format('%r%a');
    }
}
if (!function_exists('wf_project_task_is_closed')) {
    function wf_project_task_is_closed(array $task): bool
    {
        $statusCode = strtoupper(trim((string)($task['StatusCode'] ?? '')));
        $statusName = strtoupper(trim((string)($task['StatusName'] ?? '')));
        return trim((string)($task['CompletedAt'] ?? '')) !== ''
            || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
    }
}

$record = is_array($record ?? null) ? $record : [];
$users = is_array($users ?? null) ? $users : [];
$projectTasks = is_array($projectTasks ?? null) ? $projectTasks : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$roleOptions = is_array($roleOptions ?? null) && $roleOptions !== []
    ? $roleOptions
    : [
        'MEMBER' => 'workflow_project_role_member',
        'LEAD' => 'workflow_project_role_lead',
        'OBSERVER' => 'workflow_project_role_observer',
    ];
$tableInstalled = !empty($tableInstalled);
$projectId = (int)($record['WorkflowProjectID'] ?? 0);
$projectUserIDs = [];
foreach (is_array($record['ProjectUserIDs'] ?? null) ? $record['ProjectUserIDs'] : [] as $projectUserID) {
    $projectUserID = (int)$projectUserID;
    if ($projectUserID > 0) {
        $projectUserIDs[$projectUserID] = true;
    }
}
$projectUserRoles = [];
foreach (is_array($record['ProjectUserRoles'] ?? null) ? $record['ProjectUserRoles'] : [] as $projectUserID => $projectRoleCode) {
    $projectUserID = (int)$projectUserID;
    $projectRoleCode = strtoupper(trim((string)$projectRoleCode));
    if ($projectUserID > 0 && array_key_exists($projectRoleCode, $roleOptions)) {
        $projectUserRoles[$projectUserID] = $projectRoleCode;
    }
}

$screenHeader = [
    'title' => $projectId > 0 ? __t('workflow_project_edit') : __t('workflow_project_create'),
    'icon' => 'bi-kanban',
];
$returnTo = trim((string)($returnTo ?? ''));
$backUrl = trim((string)($backUrl ?? ''));
if ($backUrl === '') {
    $backUrl = $returnTo !== '' ? $returnTo : 'index.php?route=workflow-projects/list';
}

$ganttRows = [];
$unscheduledTasks = [];
$timelineStart = wf_project_date($record['StartDate'] ?? null);
$timelineEnd = wf_project_date($record['TargetEndDate'] ?? null);

foreach ($projectTasks as $taskRow) {
    $taskStart = wf_project_date($taskRow['PlannedStartDate'] ?? null)
        ?? wf_project_date($taskRow['DueDate'] ?? null);
    $taskEnd = wf_project_date($taskRow['PlannedEndDate'] ?? null)
        ?? wf_project_date($taskRow['DueDate'] ?? null)
        ?? $taskStart;

    if (!$taskStart || !$taskEnd) {
        $unscheduledTasks[] = $taskRow;
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
        'task' => $taskRow,
        'start' => $taskStart,
        'end' => $taskEnd,
        'closed' => wf_project_task_is_closed($taskRow),
        'percent' => max(0, min(100, (float)($taskRow['PercentComplete'] ?? 0))),
    ];
}

if ($timelineStart && $timelineEnd && $timelineEnd < $timelineStart) {
    $timelineEnd = $timelineStart;
}
$timelineDays = ($timelineStart && $timelineEnd)
    ? max(1, wf_project_days_between($timelineStart, $timelineEnd) + 1)
    : 1;
$today = new DateTimeImmutable(date('Y-m-d'));
$todayOffsetPercent = null;
if ($timelineStart && $timelineEnd && $today >= $timelineStart && $today <= $timelineEnd) {
    $todayOffsetPercent = max(0, min(100, (wf_project_days_between($timelineStart, $today) / $timelineDays) * 100));
}
?>

<style>
  .workflow-gantt {
    border: 1px solid #dfe7ef;
    border-radius: .5rem;
    overflow: hidden;
    background: #fff;
  }
  .workflow-gantt-header,
  .workflow-gantt-row {
    display: grid;
    grid-template-columns: minmax(15rem, 22rem) minmax(28rem, 1fr);
  }
  .workflow-gantt-header {
    background: #f7f9fc;
    border-bottom: 1px solid #dfe7ef;
    font-size: .8rem;
    font-weight: 600;
    color: #526274;
  }
  .workflow-gantt-header > div,
  .workflow-gantt-row > div {
    padding: .55rem .75rem;
  }
  .workflow-gantt-row + .workflow-gantt-row {
    border-top: 1px solid #edf1f5;
  }
  .workflow-gantt-task {
    min-width: 0;
  }
  .workflow-gantt-task-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .workflow-gantt-track {
    position: relative;
    min-height: 2rem;
    background:
      repeating-linear-gradient(
        to right,
        #f8fafc 0,
        #f8fafc calc(10% - 1px),
        #eef3f8 calc(10% - 1px),
        #eef3f8 10%
      );
    border-radius: .35rem;
  }
  .workflow-gantt-bar {
    position: absolute;
    top: .35rem;
    height: 1.3rem;
    border-radius: .35rem;
    background: #376fa8;
    color: #fff;
    font-size: .74rem;
    line-height: 1.3rem;
    overflow: hidden;
    white-space: nowrap;
    box-shadow: 0 .1rem .25rem rgba(21, 42, 63, .16);
  }
  .workflow-gantt-bar.is-complete {
    background: #2f8750;
  }
  .workflow-gantt-progress {
    position: absolute;
    inset: 0 auto 0 0;
    background: rgba(255, 255, 255, .24);
  }
  .workflow-gantt-bar-label {
    position: relative;
    z-index: 1;
    padding: 0 .45rem;
  }
  .workflow-gantt-today {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #d14444;
  }
  .workflow-gantt-scale {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    font-size: .76rem;
    color: #657386;
  }
  .project-edit-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    border-bottom: 1px solid #e4ebf2;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
  }
  .project-edit-actions {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
  }
  .project-edit-section {
    padding-top: 1rem;
  }
  .project-edit-section + .project-edit-section {
    border-top: 1px solid #edf1f5;
    margin-top: 1.1rem;
  }
  .project-edit-section-title {
    font-size: .95rem;
    font-weight: 700;
    margin-bottom: .85rem;
  }
  .project-edit-team-list {
    max-height: 18rem;
  }
  .project-edit-team-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(8.5rem, 11rem);
    gap: .75rem;
    align-items: center;
  }
  .project-edit-team-main {
    display: flex;
    align-items: center;
    gap: .55rem;
    min-width: 0;
  }
  .project-edit-team-label {
    min-width: 0;
  }
  .project-edit-team-role .form-select {
    min-height: 2rem;
  }
  .project-edit-footer {
    border-top: 1px solid #e4ebf2;
    padding-top: 1rem;
    margin-top: 1.1rem;
  }
  .project-edit-task-section {
    border-top: 1px solid #e4ebf2;
  }
  @media (max-width: 900px) {
    .workflow-gantt {
      overflow-x: auto;
    }
    .workflow-gantt-header,
    .workflow-gantt-row {
      min-width: 48rem;
    }
    .project-edit-team-row {
      grid-template-columns: 1fr;
    }
    .project-edit-team-role {
      padding-left: 1.8rem;
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

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          <?= h(__t('workflow_project_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=workflow-projects/save" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="WorkflowProjectID" value="<?= $projectId ?>">
        <input type="hidden" name="returnTo" value="<?= h($returnTo) ?>">

        <div class="project-edit-toolbar">
          <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
          <div class="project-edit-actions">
            <?php if ($projectId > 0): ?>
              <a href="index.php?route=workflow-projects/summary&id=<?= $projectId ?>" class="btn btn-outline-info">
                <i class="bi bi-speedometer2 me-1"></i><?= h(__t('workflow_project_summary')) ?>
              </a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" <?= !$tableInstalled ? 'disabled' : '' ?>>
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
              <i class="bi bi-save me-1"></i><?= h(__t('workflow_project_save')) ?>
            </button>
          </div>
        </div>

        <div class="row g-4 align-items-start">
          <div class="col-12 col-xl-7">
            <section class="project-edit-section">
              <div class="project-edit-section-title"><?= h(__t('workflow_project_project_plan')) ?></div>
              <div class="row g-3">
                <div class="col-12 col-lg-4">
                  <label class="form-label" for="ProjectCode"><?= h(__t('workflow_project_code')) ?></label>
                  <input type="text" class="form-control" id="ProjectCode" name="ProjectCode" value="<?= h((string)($record['ProjectCode'] ?? '')) ?>" maxlength="50" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-12 col-lg-8">
                  <label class="form-label" for="ProjectName"><?= h(__t('workflow_project_name')) ?></label>
                  <input type="text" class="form-control" id="ProjectName" name="ProjectName" value="<?= h((string)($record['ProjectName'] ?? '')) ?>" maxlength="255" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                  <div class="invalid-feedback"><?= h(__t('workflow_project_name_required')) ?></div>
                </div>
                <div class="col-12 col-lg-5">
                  <label class="form-label" for="ProjectOwnerUserID"><?= h(__t('workflow_project_owner')) ?></label>
                  <select class="form-select" id="ProjectOwnerUserID" name="ProjectOwnerUserID" <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <option value=""><?= h(__t('workflow_project_no_owner')) ?></option>
                    <?php foreach ($users as $user): ?>
                      <?php
                        $userId = (int)($user['UserID'] ?? 0);
                        if ($userId <= 0) {
                            continue;
                        }
                        $label = trim((string)($user['DisplayName'] ?? ''));
                        if ($label === '') {
                            $label = trim((string)($user['Username'] ?? __t('user_number', ['id' => $userId])));
                        }
                      ?>
                      <option value="<?= $userId ?>" <?= (int)($record['ProjectOwnerUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-sm-7 col-lg-4">
                  <label class="form-label" for="ProjectStatusCode"><?= h(__t('status')) ?></label>
                  <select class="form-select" id="ProjectStatusCode" name="ProjectStatusCode" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <?php foreach ($statusOptions as $code => $labelKey): ?>
                      <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['ProjectStatusCode'] ?? 'PLANNED')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12 col-sm-5 col-lg-3 d-flex align-items-end">
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="Active" name="Active" value="1" <?= ((int)($record['Active'] ?? 1) === 1) ? 'checked' : '' ?> <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="Active"><?= h(__t('workflow_project_active')) ?></label>
                  </div>
                </div>
              </div>
            </section>

            <section class="project-edit-section">
              <div class="project-edit-section-title"><?= h(__t('workflow_project_dates')) ?></div>
              <div class="row g-3">
                <div class="col-12 col-md-4">
                  <label class="form-label" for="StartDate"><?= h(__t('workflow_project_start_date')) ?></label>
                  <input type="date" class="form-control" id="StartDate" name="StartDate" value="<?= h((string)($record['StartDate'] ?? '')) ?>" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="TargetEndDate"><?= h(__t('workflow_project_target_end_date')) ?></label>
                  <input type="date" class="form-control" id="TargetEndDate" name="TargetEndDate" value="<?= h((string)($record['TargetEndDate'] ?? '')) ?>" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="col-12 col-md-4">
                  <label class="form-label" for="ActualEndDate"><?= h(__t('workflow_project_actual_end_date')) ?></label>
                  <input type="date" class="form-control" id="ActualEndDate" name="ActualEndDate" value="<?= h((string)($record['ActualEndDate'] ?? '')) ?>" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
              </div>
            </section>

            <section class="project-edit-section">
              <div class="project-edit-section-title"><?= h(__t('description')) ?></div>
              <label class="visually-hidden" for="Description"><?= h(__t('description')) ?></label>
              <textarea class="form-control" id="Description" name="Description" rows="5" <?= !$tableInstalled ? 'disabled' : '' ?>><?= h((string)($record['Description'] ?? '')) ?></textarea>
            </section>
          </div>

          <div class="col-12 col-xl-5">
            <section class="project-edit-section">
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                <div>
                  <div class="project-edit-section-title mb-1"><?= h(__t('workflow_project_users')) ?></div>
                  <div class="text-muted small"><?= h(__t('workflow_project_users_help')) ?></div>
                </div>
                <span class="badge text-bg-secondary" data-project-user-selected-count>0 <?= h(__t('workflow_project_selected')) ?></span>
              </div>
              <div class="input-group input-group-sm mb-2">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search"
                       class="form-control"
                       id="workflowProjectUserSearch"
                       placeholder="<?= h(__t('workflow_project_user_search_placeholder')) ?>"
                       data-project-user-search
                       <?= !$tableInstalled ? 'disabled' : '' ?>>
                <button type="button" class="btn btn-outline-secondary" data-project-user-clear <?= !$tableInstalled ? 'disabled' : '' ?>>
                  <i class="bi bi-x-circle me-1"></i><?= h(__t('workflow_project_clear_users')) ?>
                </button>
              </div>
              <div class="project-edit-team-list border rounded bg-white overflow-auto" data-project-user-list>
                <?php foreach ($users as $user): ?>
                  <?php
                    $userId = (int)($user['UserID'] ?? 0);
                    if ($userId <= 0) {
                        continue;
                    }
                    $label = trim((string)($user['DisplayName'] ?? ''));
                    if ($label === '') {
                        $label = trim((string)($user['Username'] ?? __t('user_number', ['id' => $userId])));
                    }
                    $username = trim((string)($user['Username'] ?? ''));
                    $searchText = strtolower(trim($label . ' ' . $username));
                    $isSelected = isset($projectUserIDs[$userId]);
                    $selectedRole = $projectUserRoles[$userId] ?? 'MEMBER';
                  ?>
                  <div class="project-edit-team-row border-bottom px-3 py-2" data-project-user-row data-project-user-search-text="<?= h($searchText) ?>">
                    <div class="project-edit-team-main">
                      <input class="form-check-input mt-0"
                             type="checkbox"
                             id="ProjectUserID_<?= $userId ?>"
                             name="ProjectUserIDs[]"
                             value="<?= $userId ?>"
                             data-project-user-checkbox
                             <?= $isSelected ? 'checked' : '' ?>
                             <?= !$tableInstalled ? 'disabled' : '' ?>>
                      <label class="project-edit-team-label mb-0" for="ProjectUserID_<?= $userId ?>">
                        <span class="d-block text-truncate"><?= h($label) ?></span>
                        <?php if ($username !== '' && $username !== $label): ?>
                          <span class="d-block text-muted small text-truncate"><?= h($username) ?></span>
                        <?php endif; ?>
                      </label>
                    </div>
                    <div class="project-edit-team-role">
                      <label class="visually-hidden" for="ProjectUserRole_<?= $userId ?>"><?= h(__t('workflow_project_role')) ?></label>
                      <select class="form-select form-select-sm"
                              id="ProjectUserRole_<?= $userId ?>"
                              name="ProjectUserRoles[<?= $userId ?>]"
                              data-project-user-role
                              <?= !$isSelected || !$tableInstalled ? 'disabled' : '' ?>
                              <?= !$tableInstalled ? 'data-project-user-role-locked="1"' : '' ?>>
                        <?php foreach ($roleOptions as $roleCode => $roleLabelKey): ?>
                          <option value="<?= h((string)$roleCode) ?>" <?= $selectedRole === (string)$roleCode ? 'selected' : '' ?>>
                            <?= h(__t((string)$roleLabelKey)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="text-center text-muted small py-3 d-none" data-project-user-empty>
                  <?= h(__t('workflow_project_no_user_matches')) ?>
                </div>
              </div>
            </section>
          </div>
        </div>

        <div class="project-edit-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
          <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
          <button type="submit" class="btn btn-primary" <?= !$tableInstalled ? 'disabled' : '' ?>>
            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
            <i class="bi bi-save me-1"></i><?= h(__t('workflow_project_save')) ?>
          </button>
        </div>
      </form>

      <?php if ($projectId > 0): ?>
        <section id="workflow-project-gantt" class="project-edit-section project-edit-task-section mt-4">
          <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
            <div>
              <h2 class="project-edit-section-title mb-1"><?= h(__t('workflow_project_tasks')) ?></h2>
              <div class="text-muted small"><?= h(__t('workflow_project_gantt_help')) ?></div>
            </div>
            <div class="d-flex gap-2">
              <a href="index.php?route=workflow/edit&workflowProjectID=<?= $projectId ?>" class="btn btn-sm btn-outline-success">
                <i class="bi bi-plus-lg me-1"></i><?= h(__t('workflow_project_task')) ?>
              </a>
              <a href="index.php?route=workflow/list&workflowProjectID=<?= $projectId ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-list-task me-1"></i><?= h(__t('workflow_project_view_tasks')) ?>
              </a>
            </div>
          </div>

          <?php if ($ganttRows === []): ?>
            <div class="alert alert-light border mb-0">
              <?= h(__t('workflow_project_no_scheduled_tasks')) ?>
            </div>
          <?php else: ?>
            <div class="workflow-gantt">
              <div class="workflow-gantt-header">
                <div><?= h(__t('workflow_project_tasks')) ?></div>
                <div>
                  <div class="workflow-gantt-scale">
                    <span><?= h($timelineStart ? $timelineStart->format('Y-m-d') : '') ?></span>
                    <span><?= h(__t('workflow_project_timeline')) ?></span>
                    <span><?= h($timelineEnd ? $timelineEnd->format('Y-m-d') : '') ?></span>
                  </div>
                </div>
              </div>

              <?php foreach ($ganttRows as $ganttRow): ?>
                <?php
                  $taskRow = (array)$ganttRow['task'];
                  $taskId = (int)($taskRow['WorkflowTaskID'] ?? 0);
                  $taskTitle = trim((string)($taskRow['Title'] ?? ''));
                  if ($taskTitle === '') {
                      $taskTitle = __t('workflow_task_number', ['id' => $taskId]);
                  }
                  $barStartDate = $ganttRow['start'];
                  $barEndDate = $ganttRow['end'];
                  $offsetDays = $timelineStart ? max(0, wf_project_days_between($timelineStart, $barStartDate)) : 0;
                  $durationDays = max(1, wf_project_days_between($barStartDate, $barEndDate) + 1);
                  $left = max(0.0, min(100.0, ($offsetDays / $timelineDays) * 100));
                  $availableWidth = max(0.5, 100.0 - $left);
                  $width = min($availableWidth, max(1.8, ($durationDays / $timelineDays) * 100));
                  $percent = (int)round((float)$ganttRow['percent']);
                  $isComplete = !empty($ganttRow['closed']) || $percent >= 100;
                  $assignee = trim((string)($taskRow['AssignedToName'] ?? ''));
                  $hasParent = (int)($taskRow['ParentWorkflowTaskID'] ?? 0) > 0;
                ?>
                <div class="workflow-gantt-row">
                  <div class="workflow-gantt-task <?= $hasParent ? 'ps-4' : '' ?>">
                    <div class="workflow-gantt-task-title">
                      <?php if ($hasParent): ?><i class="bi bi-arrow-return-right text-muted me-1"></i><?php endif; ?>
                      <a href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $projectId ?>">
                        <?= h($taskTitle) ?>
                      </a>
                    </div>
                    <div class="text-muted small">
                      #<?= $taskId ?>
                      <?php if ($assignee !== ''): ?> &middot; <?= h($assignee) ?><?php endif; ?>
                      &middot; <?= h($barStartDate->format('Y-m-d')) ?> - <?= h($barEndDate->format('Y-m-d')) ?>
                    </div>
                  </div>
                  <div>
                    <div class="workflow-gantt-track">
                      <?php if ($todayOffsetPercent !== null): ?>
                        <span class="workflow-gantt-today"
                              style="left: <?= h(number_format($todayOffsetPercent, 4, '.', '')) ?>%;"
                              title="<?= h(__t('workflow_project_today')) ?>"></span>
                      <?php endif; ?>
                      <div class="workflow-gantt-bar <?= $isComplete ? 'is-complete' : '' ?>"
                           style="left: <?= h(number_format($left, 4, '.', '')) ?>%; width: <?= h(number_format($width, 4, '.', '')) ?>%;"
                           title="<?= h($taskTitle . ' (' . $barStartDate->format('Y-m-d') . ' - ' . $barEndDate->format('Y-m-d') . ')') ?>">
                        <span class="workflow-gantt-progress" style="width: <?= h((string)$percent) ?>%;"></span>
                        <span class="workflow-gantt-bar-label"><?= h($percent) ?>%</span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($unscheduledTasks !== []): ?>
            <div class="mt-3">
              <div class="fw-semibold small mb-2"><?= h(__t('workflow_project_unscheduled_tasks')) ?></div>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($unscheduledTasks as $taskRow): ?>
                  <?php
                    $taskId = (int)($taskRow['WorkflowTaskID'] ?? 0);
                    $taskTitle = trim((string)($taskRow['Title'] ?? ''));
                    if ($taskTitle === '') {
                        $taskTitle = __t('workflow_task_number', ['id' => $taskId]);
                    }
                  ?>
                  <a class="badge text-bg-light border text-decoration-none"
                     href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $projectId ?>">
                    #<?= $taskId ?> <?= h($taskTitle) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  const projectUserSearch = document.querySelector('[data-project-user-search]');
  const projectUserRows = Array.from(document.querySelectorAll('[data-project-user-row]'));
  const projectUserEmpty = document.querySelector('[data-project-user-empty]');
  const projectUserCount = document.querySelector('[data-project-user-selected-count]');
  const projectUserClear = document.querySelector('[data-project-user-clear]');
  const syncProjectUserRow = row => {
    const input = row.querySelector('[data-project-user-checkbox]');
    const role = row.querySelector('[data-project-user-role]');
    if (!role || role.getAttribute('data-project-user-role-locked') === '1') return;
    role.disabled = !(input && input.checked);
  };
  const updateProjectUserCount = () => {
    if (!projectUserCount) return;
    const selected = projectUserRows.filter(row => {
      const input = row.querySelector('[data-project-user-checkbox]');
      return input && input.checked;
    }).length;
    projectUserCount.textContent = selected + ' <?= h(__t('workflow_project_selected')) ?>';
  };
  const filterProjectUsers = () => {
    const term = (projectUserSearch ? projectUserSearch.value : '').trim().toLowerCase();
    let visible = 0;
    projectUserRows.forEach(row => {
      const haystack = row.getAttribute('data-project-user-search-text') || '';
      const show = term === '' || haystack.includes(term);
      row.classList.toggle('d-none', !show);
      if (show) visible++;
    });
    if (projectUserEmpty) {
      projectUserEmpty.classList.toggle('d-none', visible !== 0);
    }
  };
  if (projectUserSearch) {
    projectUserSearch.addEventListener('input', filterProjectUsers);
  }
  if (projectUserClear) {
    projectUserClear.addEventListener('click', () => {
      projectUserRows.forEach(row => {
        const input = row.querySelector('[data-project-user-checkbox]');
        if (input) input.checked = false;
        syncProjectUserRow(row);
      });
      updateProjectUserCount();
    });
  }
  projectUserRows.forEach(row => {
    const input = row.querySelector('[data-project-user-checkbox]');
    syncProjectUserRow(row);
    if (input) {
      input.addEventListener('change', () => {
        syncProjectUserRow(row);
        updateProjectUserCount();
      });
    }
  });
  updateProjectUserCount();
  filterProjectUsers();

  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
        form.classList.add('was-validated');
        return;
      }
      const submitter = event.submitter || form.querySelector('button[type="submit"]');
      if (submitter) {
        const spinner = submitter.querySelector('.spinner-border');
        const icon = submitter.querySelector('.bi');
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        submitter.disabled = true;
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
