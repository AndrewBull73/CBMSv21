<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
$title = $title ?? __t('workflow_tasks');
$tasks = is_array($tasks ?? null) ? $tasks : [];
$users = is_array($users ?? null) ? $users : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$workflowProjectsInstalled = !empty($workflowProjectsInstalled);
$total = (int) ($total ?? 0);
$totalPages = (int) ($totalPages ?? 0);
$page = max(1, (int) ($page ?? 1));
$pageSize = max(1, (int) ($pageSize ?? 25));
$q = (string) ($q ?? '');
$typeID = isset($typeID) && $typeID !== '' ? (int) $typeID : null;
$statusID = isset($statusID) && $statusID !== '' ? (int) $statusID : null;
$assignedToUserID = isset($assignedToUserID) && $assignedToUserID !== '' ? (int) $assignedToUserID : null;
$workflowProjectID = isset($workflowProjectID) && $workflowProjectID !== '' ? (int) $workflowProjectID : null;
$showAdminScope = !empty($showAdminScope);
$mine = !empty($mine);
$canEditWorkflow = !empty($canEditWorkflow);
$canViewWorkflow = !empty($canViewWorkflow);
$canAdminWorkflow = !empty($canAdminWorkflow);
$currentUserId = (int)($currentUserId ?? 0);
$taskScope = (string)($taskScope ?? ($mine ? 'received' : 'all'));
if (!in_array($taskScope, ['received', 'created', 'all'], true)) {
    $taskScope = $mine ? 'received' : 'all';
}
$isRecipientListView = $mine && $taskScope === 'received' && !$canAdminWorkflow;
if ($isRecipientListView) {
    $title = __t('workflow_task_received_tasks');
}
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'open' => 0, 'overdue' => 0, 'due_today' => 0, 'due_soon' => 0, 'closed' => 0];
$dueState = strtolower(trim((string)($dueState ?? ($_GET['due_state'] ?? ''))));
if (!in_array($dueState, ['overdue', 'today', 'soon'], true)) {
    $dueState = '';
}

$isIframe = !empty($_GET['iframe']);
$status = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['open', 'closed'], true)) {
    $status = '';
}
$taskState = $status === 'open' ? 'open' : ($status === 'closed' ? 'closed' : 'all');

$typesRaw = is_array($types ?? null) ? $types : [];
$statusesRaw = is_array($statuses ?? null) ? $statuses : [];

$typesMap = [];
foreach ($typesRaw as $k => $v) {
    $typesMap[(int) $k] = (string) $v;
}

$statusesMap = [];
foreach ($statusesRaw as $k => $v) {
    $statusesMap[(int) $k] = (string) $v;
}

$usersMap = [];
foreach ($users as $u) {
    $uid = (int) ($u['UserID'] ?? 0);
    if ($uid > 0) {
        $usersMap[$uid] = (string) ($u['DisplayName'] ?? $u['Username'] ?? __t('user_number', ['id' => $uid]));
    }
}

$workflowProjectsMap = [];
foreach ($workflowProjects as $project) {
    $projectId = (int)($project['WorkflowProjectID'] ?? 0);
    if ($projectId <= 0) {
        continue;
    }
    $label = trim((string)($project['ProjectName'] ?? ''));
    if ($label === '') {
        $label = __t('workflow_project_number', ['id' => $projectId]);
    }
    if (!empty($project['ProjectCode'])) {
        $label = (string)$project['ProjectCode'] . ' - ' . $label;
    }
    $workflowProjectsMap[$projectId] = $label;
}

function wf_format_date(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y', $ts) : '';
}

function wf_format_datetime(?string $dt): string
{
    $raw = trim((string)$dt);
    if ($raw === '') {
        return '';
    }
    $normalized = preg_replace('/(\.\d{6})\d+$/', '$1', $raw) ?? $raw;
    try {
        $utc = new DateTimeImmutable($normalized, new DateTimeZone('UTC'));
        return $utc->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        $ts = strtotime($raw . ' UTC');
        return $ts ? date('d/m/Y H:i', $ts) : $raw;
    }
}

function wf_is_closed_task(array $task): bool
{
    $statusCode = strtoupper(trim((string) ($task['StatusCode'] ?? '')));
    $statusName = strtoupper(trim((string) ($task['StatusName'] ?? '')));
    return !empty($task['CompletedAt'])
        || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
        || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
}

function wf_is_overdue(array $task): bool
{
    if (wf_is_closed_task($task)) {
        return false;
    }
    $dueDate = (string) ($task['DueDate'] ?? '');
    if ($dueDate === '') {
        return false;
    }
    $ts = strtotime($dueDate);
    return $ts !== false && strtotime(date('Y-m-d')) > strtotime(date('Y-m-d', $ts));
}

function wf_due_state(array $task): string
{
    if (wf_is_closed_task($task)) {
        return 'closed';
    }
    $dueDate = (string) ($task['DueDate'] ?? '');
    if ($dueDate === '') {
        return '';
    }
    $ts = strtotime($dueDate);
    if ($ts === false) {
        return '';
    }

    $today = strtotime(date('Y-m-d'));
    $due = strtotime(date('Y-m-d', $ts));
    if ($due < $today) {
        return 'overdue';
    }
    if ($due === $today) {
        return 'today';
    }
    if ($due <= strtotime('+7 days', $today)) {
        return 'soon';
    }

    return 'future';
}

function wf_due_badge_class(string $dueState): string
{
    return match ($dueState) {
        'overdue' => 'text-bg-danger',
        'today' => 'text-bg-warning',
        'soon' => 'text-bg-info',
        default => 'text-bg-light border text-muted',
    };
}

function wf_due_label(string $dueState): string
{
    return match ($dueState) {
        'overdue' => __t('workflow_task_overdue'),
        'today' => __t('workflow_task_due_today'),
        'soon' => __t('workflow_task_due_soon'),
        default => '',
    };
}

function wf_status_badge_class(array $task): string
{
    if (wf_is_closed_task($task)) {
        return 'text-bg-success';
    }
    if (wf_is_overdue($task)) {
        return 'text-bg-danger';
    }
    $statusCode = strtoupper(trim((string) ($task['StatusCode'] ?? '')));
    if (in_array($statusCode, ['INPROGRESS', 'IN_PROGRESS'], true)) {
        return 'text-bg-warning';
    }
    return 'text-bg-primary';
}

function wf_priority_badge_class(?string $priorityCode): string
{
    return match (strtoupper(trim((string) $priorityCode))) {
        'URGENT' => 'text-bg-danger',
        'HIGH' => 'text-bg-warning',
        'LOW' => 'text-bg-secondary',
        default => 'text-bg-info',
    };
}

function wf_priority_label(?string $priorityCode): string
{
    $code = strtoupper(trim((string) $priorityCode));
    return match ($code) {
        'URGENT' => __t('workflow_task_priority_urgent'),
        'HIGH' => __t('workflow_task_priority_high'),
        'LOW' => __t('workflow_task_priority_low'),
        default => __t('workflow_task_priority_normal'),
    };
}

function wf_build_query(array $params): string
{
    $context = wf_context_params();
    $merged = $params + $context;
    if (($merged['scope_dataobject_code'] ?? null) === '') {
        unset($merged['scope_dataobject_name']);
    }
    $filtered = array_filter($merged, static function ($value, $key): bool {
        return $value !== null && ($value !== '' || $key === 'scope_dataobject_code');
    }, ARRAY_FILTER_USE_BOTH);
    return 'index.php?' . http_build_query($filtered);
}

function wf_context_params(): array
{
    $params = [];
    if ((string) ($_GET['link_context'] ?? '') === '1') {
        $params['link_context'] = '1';
    }
    if (isset($_GET['fy']) && is_numeric($_GET['fy'])) {
        $params['fy'] = (string) ((int) $_GET['fy']);
    }
    if (isset($_GET['ver']) && is_numeric($_GET['ver'])) {
        $params['ver'] = (string) ((int) $_GET['ver']);
    }
    if (array_key_exists('scope_dataobject_code', $_GET)) {
        $params['scope_dataobject_code'] = (string) $_GET['scope_dataobject_code'];
        $scopeName = trim((string) ($_GET['scope_dataobject_name'] ?? ''));
        if ($params['scope_dataobject_code'] !== '' && $scopeName !== '') {
            $params['scope_dataobject_name'] = $scopeName;
        }
    }
    return $params;
}

function wf_render_context_inputs(): string
{
    $html = '';
    foreach (wf_context_params() as $key => $value) {
        $html .= '<input type="hidden" name="' . h((string) $key) . '" value="' . h((string) $value) . '">' . PHP_EOL;
    }
    return $html;
}

$workflowTaskExportUrl = 'index.php?' . http_build_query(array_merge($_GET, ['route' => 'workflow/export-excel']));
?>

<?php if ($isIframe): ?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= h($title) ?></title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
<?php endif; ?>

<style>
  .workflow-shell {
    font-size: .95rem;
  }
  .workflow-shell > .card.shadow-sm {
    background: #fff;
    border: 1px solid #e4ebf2;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05) !important;
  }
  .workflow-shell .card-header {
    background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
    border-bottom: 1px solid #e4ebf2;
    padding: 1.05rem 1.15rem;
  }
  .workflow-shell .card-body {
    padding: 1.05rem 1.15rem;
  }
  .workflow-shell .table {
    margin-bottom: 0;
  }
  .workflow-shell .table thead th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6f7f90;
    border-bottom-width: 1px;
  }
  .workflow-shell .table td,
  .workflow-shell .table th {
    padding: .8rem .9rem;
    font-size: .89rem;
    vertical-align: top;
  }
  .workflow-shell .alert,
  .workflow-shell .workflow-metric {
    border-radius: .9rem;
  }
  .workflow-shell .form-control,
  .workflow-shell .form-select {
    font-size: .875rem;
  }
  .workflow-shell .form-label {
    font-size: .84rem;
    font-weight: 600;
    color: #415161;
    margin-bottom: .4rem;
  }
  .workflow-shell .btn {
    border-radius: .7rem;
  }
  .workflow-shell .btn.btn-sm,
  .workflow-shell .btn-group-sm .btn {
    border-radius: .65rem;
  }
  .workflow-shell .workflow-metric {
    border: 1px solid #e4ebf2;
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    padding: .85rem .95rem;
    min-height: 100%;
  }
  .workflow-shell .workflow-metric-label {
    color: #6f7f90;
    font-size: .72rem;
    letter-spacing: .04em;
    text-transform: uppercase;
  }
  .workflow-shell .workflow-metric-value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #223548;
    line-height: 1.15;
  }
  .workflow-shell .workflow-scope-strip {
    border: 1px solid #e4ebf2;
    border-radius: .9rem;
    background: #fff;
    padding: .75rem .85rem;
  }
  .workflow-shell .workflow-task-tabs {
    border-bottom: 1px solid #e4ebf2;
  }
  .workflow-shell .workflow-task-tabs .nav-link {
    font-size: .875rem;
    border-radius: .65rem .65rem 0 0;
  }
  .workflow-shell .workflow-row-overdue {
    background: #fff7f7;
  }
  .workflow-shell .workflow-row-unread td {
    background: #fffdf2;
  }
  .workflow-shell .workflow-task-title {
    font-weight: 600;
    color: #24384d;
  }
  .workflow-shell .workflow-meta-line {
    color: #6f7f90;
    font-size: .78rem;
  }
  .workflow-shell .workflow-recipient-list-summary {
    border-top: 1px solid #e4ebf2;
    border-bottom: 1px solid #e4ebf2;
    padding: .8rem 0;
  }
  .workflow-shell .workflow-recipient-list-title {
    color: #24384d;
    font-size: 1rem;
    font-weight: 700;
  }
  .workflow-shell .workflow-recipient-list-help,
  .workflow-shell .workflow-recipient-task-meta {
    color: #6f7f90;
    font-size: .82rem;
  }
  .workflow-shell .workflow-recipient-list-stats {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem .85rem;
    justify-content: flex-end;
  }
  .workflow-shell .workflow-recipient-list-stat {
    color: #415161;
    font-size: .84rem;
    white-space: nowrap;
  }
  .workflow-shell .workflow-recipient-list-stat strong {
    color: #223548;
    font-size: 1rem;
  }
  .workflow-shell .workflow-recipient-list-stat.text-danger strong {
    color: var(--bs-danger);
  }
  .workflow-shell .workflow-recipient-list-stat.text-warning strong {
    color: var(--bs-warning-text-emphasis, #997404);
  }
  .workflow-shell .workflow-recipient-task-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem .7rem;
    margin-top: .25rem;
  }
  .workflow-shell .workflow-recipient-task-meta .badge {
    font-size: .7rem;
  }
  @media (max-width: 767.98px) {
    .workflow-shell .workflow-recipient-list-stats {
      justify-content: flex-start;
    }
  }
</style>

<div class="workflow-shell">
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center">
      <i class="bi bi-clipboard-check me-2"></i>
      <strong><?= h($title) ?></strong>
      <?php if ($status === 'open'): ?>
        <small class="text-muted ms-2"><?= h(__t('open_only')) ?></small>
      <?php elseif ($status === 'closed'): ?>
        <small class="text-muted ms-2"><?= h(__t('closed_only')) ?></small>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
      <button type="button" id="workflow-tasks-print-btn" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i><?= h(__t('print')) ?>
      </button>
      <a id="workflow-tasks-export-excel-btn" href="<?= h($workflowTaskExportUrl) ?>" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i><?= h(__t('export_excel')) ?>
      </a>
      <?php if ($canEditWorkflow): ?>
        <?php
        $createQs = ['route' => 'workflow/edit', 'mine' => $mine ? 1 : 0];
        if ($mine && $taskScope !== 'all') {
            $createQs['task_scope'] = $taskScope;
        }
        if ($dueState !== '') {
            $createQs['due_state'] = $dueState;
        }
        if ($workflowProjectID !== null) {
            $createQs['workflowProjectID'] = $workflowProjectID;
        }
        if ($assignedToUserID !== null) {
            $createQs['assignedToUserID'] = $assignedToUserID;
        }
        if ($isIframe) {
            $createQs['iframe'] = '1';
        }
        $createUrl = wf_build_query($createQs);
        ?>
        <a id="workflow-tasks-create-btn" href="<?= h($createUrl) ?>" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-circle me-1"></i><?= h(__t('create_task')) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <?php if ($isRecipientListView): ?>
      <div class="workflow-recipient-list-summary d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
          <div class="workflow-recipient-list-title"><?= h(__t('workflow_task_received_tasks')) ?></div>
          <div class="workflow-recipient-list-help"><?= h(__t('workflow_task_received_tasks_help')) ?></div>
        </div>
        <div class="workflow-recipient-list-stats">
          <span class="workflow-recipient-list-stat"><strong><?= (int) ($summary['open'] ?? 0) ?></strong> <?= h(__t('workflow_task_open')) ?></span>
          <span class="workflow-recipient-list-stat text-danger"><strong><?= (int) ($summary['overdue'] ?? 0) ?></strong> <?= h(__t('workflow_task_overdue')) ?></span>
          <span class="workflow-recipient-list-stat text-warning"><strong><?= (int) ($summary['due_today'] ?? 0) ?></strong> <?= h(__t('workflow_task_due_today')) ?></span>
        </div>
      </div>
    <?php else: ?>
      <div class="row g-2 mb-3">
        <div class="col-6 col-lg-2">
          <div class="workflow-metric">
            <div class="workflow-metric-label"><?= h(__t('workflow_task_open_tasks')) ?></div>
            <div class="workflow-metric-value"><?= (int) ($summary['open'] ?? 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="workflow-metric">
            <div class="workflow-metric-label"><?= h(__t('workflow_task_overdue')) ?></div>
            <div class="workflow-metric-value text-danger"><?= (int) ($summary['overdue'] ?? 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="workflow-metric">
            <div class="workflow-metric-label"><?= h(__t('workflow_task_due_today')) ?></div>
            <div class="workflow-metric-value text-warning"><?= (int) ($summary['due_today'] ?? 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="workflow-metric">
            <div class="workflow-metric-label"><?= h(__t('workflow_task_due_soon')) ?></div>
            <div class="workflow-metric-value text-info"><?= (int) ($summary['due_soon'] ?? 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="workflow-metric">
            <div class="workflow-metric-label"><?= h(__t('workflow_task_closed_tasks')) ?></div>
            <div class="workflow-metric-value text-success"><?= (int) ($summary['closed'] ?? 0) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="workflow-metric">
            <div class="workflow-metric-label"><?= h(__t('workflow_task_total_tasks')) ?></div>
            <div class="workflow-metric-value"><?= (int) ($summary['total'] ?? 0) ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php
    $stateTabBase = [
        'route' => 'workflow/list',
        'q' => $q,
        'typeID' => $typeID,
        'statusID' => $statusID,
        'pageSize' => $pageSize,
        'mine' => $mine ? 1 : ($showAdminScope ? 0 : null),
        'task_scope' => $mine && $taskScope !== 'all' ? $taskScope : null,
        'workflowProjectID' => $workflowProjectID,
        'assignedToUserID' => $assignedToUserID,
    ];
    if ($isIframe) {
        $stateTabBase['iframe'] = '1';
    }
    ?>
    <ul class="nav nav-tabs workflow-task-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link <?= $taskState === 'open' ? 'active' : '' ?>"
           href="<?= h(wf_build_query(array_filter($stateTabBase + ['status' => 'open'], static fn($v) => $v !== null && $v !== ''))) ?>">
          <i class="bi bi-list-task me-1"></i><?= h(__t('workflow_task_open')) ?>
          <span class="badge text-bg-light border ms-1"><?= (int)($summary['open'] ?? 0) ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $taskState === 'closed' ? 'active' : '' ?>"
           href="<?= h(wf_build_query(array_filter($stateTabBase + ['status' => 'closed'], static fn($v) => $v !== null && $v !== ''))) ?>">
          <i class="bi bi-check2-circle me-1"></i><?= h(__t('workflow_task_closed')) ?>
          <span class="badge text-bg-light border ms-1"><?= (int)($summary['closed'] ?? 0) ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $taskState === 'all' ? 'active' : '' ?>"
           href="<?= h(wf_build_query(array_filter($stateTabBase, static fn($v) => $v !== null && $v !== ''))) ?>">
          <i class="bi bi-collection me-1"></i><?= h(__t('workflow_task_all')) ?>
          <span class="badge text-bg-light border ms-1"><?= (int)($summary['total'] ?? 0) ?></span>
        </a>
      </li>
    </ul>

    <?php
    $dueFilterBase = [
        'route' => 'workflow/list',
        'q' => $q,
        'typeID' => $typeID,
        'statusID' => $statusID,
        'status' => 'open',
        'pageSize' => $pageSize,
        'mine' => $mine ? 1 : ($showAdminScope ? 0 : null),
        'task_scope' => $mine && $taskScope !== 'all' ? $taskScope : null,
        'workflowProjectID' => $workflowProjectID,
        'assignedToUserID' => $assignedToUserID,
    ];
    if ($isIframe) {
        $dueFilterBase['iframe'] = '1';
    }
    ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span class="small text-muted fw-semibold me-1"><?= h(__t('workflow_task_due_filter')) ?></span>
      <a class="btn btn-sm <?= $taskState === 'open' && $dueState === '' ? 'btn-primary' : 'btn-outline-secondary' ?>"
         href="<?= h(wf_build_query(array_filter($dueFilterBase, static fn($v) => $v !== null && $v !== ''))) ?>">
        <?= h(__t('workflow_task_all_open')) ?>
      </a>
      <a class="btn btn-sm <?= $dueState === 'overdue' ? 'btn-danger' : 'btn-outline-danger' ?>"
         href="<?= h(wf_build_query(array_filter($dueFilterBase + ['due_state' => 'overdue'], static fn($v) => $v !== null && $v !== ''))) ?>">
        <?= h(__t('workflow_task_overdue')) ?>
        <span class="badge text-bg-light border ms-1"><?= (int)($summary['overdue'] ?? 0) ?></span>
      </a>
      <a class="btn btn-sm <?= $dueState === 'today' ? 'btn-warning' : 'btn-outline-warning' ?>"
         href="<?= h(wf_build_query(array_filter($dueFilterBase + ['due_state' => 'today'], static fn($v) => $v !== null && $v !== ''))) ?>">
        <?= h(__t('workflow_task_due_today')) ?>
        <span class="badge text-bg-light border ms-1"><?= (int)($summary['due_today'] ?? 0) ?></span>
      </a>
      <a class="btn btn-sm <?= $dueState === 'soon' ? 'btn-info' : 'btn-outline-info' ?>"
         href="<?= h(wf_build_query(array_filter($dueFilterBase + ['due_state' => 'soon'], static fn($v) => $v !== null && $v !== ''))) ?>">
        <?= h(__t('workflow_task_due_soon')) ?>
        <span class="badge text-bg-light border ms-1"><?= (int)($summary['due_soon'] ?? 0) ?></span>
      </a>
    </div>

    <?php require __DIR__ . '/_SelectedProjectCue.php'; ?>

    <?php if ($mine): ?>
      <?php
      $taskTabBase = [
          'route' => 'workflow/list',
          'q' => $q,
          'typeID' => $typeID,
          'statusID' => $statusID,
          'status' => $status,
          'due_state' => $dueState,
          'pageSize' => $pageSize,
          'mine' => 1,
          'workflowProjectID' => $workflowProjectID,
      ];
      if ($isIframe) {
          $taskTabBase['iframe'] = '1';
      }
      ?>
      <ul class="nav nav-tabs workflow-task-tabs mb-3">
        <li class="nav-item">
          <a class="nav-link <?= $taskScope === 'received' ? 'active' : '' ?>"
             href="<?= h(wf_build_query(array_filter($taskTabBase + ['task_scope' => 'received'], static fn($v) => $v !== null && $v !== ''))) ?>">
            <i class="bi bi-inbox me-1"></i><?= h(__t('workflow_task_received')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $taskScope === 'created' ? 'active' : '' ?>"
             href="<?= h(wf_build_query(array_filter($taskTabBase + ['task_scope' => 'created'], static fn($v) => $v !== null && $v !== ''))) ?>">
            <i class="bi bi-send me-1"></i><?= h(__t('workflow_task_created_by_me')) ?>
          </a>
        </li>
      </ul>
    <?php endif; ?>

    <?php if ($showAdminScope && !$isIframe): ?>
      <div class="workflow-scope-strip d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <div class="fw-semibold small"><?= h(__t('workflow_task_scope')) ?></div>
          <div class="text-muted small"><?= h(__t('workflow_task_scope_help')) ?></div>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= h(__t('workflow_task_scope')) ?>">
          <?php
          $scopeBase = ['route' => 'workflow/list', 'q' => $q, 'typeID' => $typeID, 'statusID' => $statusID, 'status' => $status, 'pageSize' => $pageSize, 'workflowProjectID' => $workflowProjectID];
          if ($dueState !== '') {
              $scopeBase['due_state'] = $dueState;
          }
          ?>
          <a class="btn <?= $mine ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= h(wf_build_query(array_filter($scopeBase + ['mine' => 1], static fn($v) => $v !== null && $v !== ''))) ?>">
            <?= h(__t('workflow_task_my_tasks')) ?>
          </a>
          <a class="btn <?= !$mine ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= h(wf_build_query(array_filter($scopeBase + ['mine' => 0], static fn($v) => $v !== null && $v !== ''))) ?>">
            <?= h(__t('workflow_task_all_tasks')) ?>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php
    $filterBase = ['route' => 'workflow/list', 'page' => 1];
    if ($status !== '') {
        $filterBase['status'] = $status;
    }
    if ($dueState !== '') {
        $filterBase['due_state'] = $dueState;
    }
    if ($workflowProjectID !== null) {
        $filterBase['workflowProjectID'] = $workflowProjectID;
    }
    if ($showAdminScope) {
        $filterBase['mine'] = $mine ? 1 : 0;
    }
    if ($mine && $taskScope !== 'all') {
        $filterBase['task_scope'] = $taskScope;
    }
    if ($isIframe) {
        $filterBase['iframe'] = '1';
    }
    ?>
    <form method="get" action="index.php" id="workflow-tasks-filter-form" class="row g-2 align-items-end mb-3">
      <?php foreach ($filterBase as $k => $v): ?>
        <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
      <?php endforeach; ?>
      <?= wf_render_context_inputs() ?>

      <div class="col-md-3">
        <label for="q" class="form-label"><?= __t('search') ?></label>
        <input type="text" id="q" name="q" class="form-control" value="<?= h($q) ?>" placeholder="<?= h(__t('search_placeholder')) ?>">
      </div>

      <div class="col-md-2">
        <label for="typeID" class="form-label"><?= h(__t('type')) ?></label>
        <select id="typeID" name="typeID" class="form-select">
          <option value=""><?= h(__t('all_types')) ?></option>
          <?php foreach ($typesMap as $id => $name): ?>
            <option value="<?= h((string) $id) ?>" <?= $typeID === $id ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label for="statusID" class="form-label"><?= h(__t('status')) ?></label>
        <select id="statusID" name="statusID" class="form-select">
          <option value=""><?= h(__t('all_statuses')) ?></option>
          <?php foreach ($statusesMap as $id => $name): ?>
            <option value="<?= h((string) $id) ?>" <?= $statusID === $id ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($workflowProjectsInstalled): ?>
        <div class="col-md-2">
          <label for="workflowProjectID" class="form-label"><?= h(__t('workflow_project_project')) ?></label>
          <select id="workflowProjectID" name="workflowProjectID" class="form-select">
            <option value=""><?= h(__t('workflow_project_all_projects')) ?></option>
            <?php foreach ($workflowProjectsMap as $id => $name): ?>
              <option value="<?= h((string) $id) ?>" <?= $workflowProjectID === $id ? 'selected' : '' ?>><?= h($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if ($showAdminScope && !$mine && !$isIframe): ?>
        <div class="col-md-3">
          <label for="assignedToUserID" class="form-label"><?= h(__t('workflow_task_assigned_to')) ?></label>
          <select id="assignedToUserID" name="assignedToUserID" class="form-select">
            <option value=""><?= h(__t('workflow_task_all_users')) ?></option>
            <?php foreach ($usersMap as $uid => $label): ?>
              <option value="<?= h((string) $uid) ?>" <?= $assignedToUserID === $uid ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-md-2">
        <label for="pageSize" class="form-label"><?= __t('page_size') ?></label>
        <select id="pageSize" name="pageSize" class="form-select" onchange="this.form.submit()">
          <?php foreach ([10, 25, 50, 100] as $ps): ?>
            <option value="<?= $ps ?>" <?= $pageSize === $ps ? 'selected' : '' ?>><?= h(__t('per_page', ['count' => $ps])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php
      $resetQs = ['route' => 'workflow/list'];
      if ($status !== '') {
          $resetQs['status'] = $status;
      }
      if ($dueState !== '') {
          $resetQs['due_state'] = $dueState;
      }
      if ($workflowProjectID !== null) {
          $resetQs['workflowProjectID'] = $workflowProjectID;
      }
      if ($showAdminScope) {
          $resetQs['mine'] = $mine ? 1 : 0;
      }
      if ($mine && $taskScope !== 'all') {
          $resetQs['task_scope'] = $taskScope;
      }
      if ($isIframe) {
          $resetQs['iframe'] = '1';
      }
      ?>
      <div class="col-md-12 d-flex gap-2">
        <button type="submit" id="workflow-tasks-filter-btn" class="btn btn-primary btn-sm">
          <i class="bi bi-search me-1"></i><?= __t('filter') ?>
        </button>
        <a id="workflow-tasks-reset-btn" href="<?= h(wf_build_query($resetQs)) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-repeat me-1"></i><?= __t('reset') ?>
        </a>
      </div>
    </form>

    <div class="table-responsive">
      <table id="workflow-tasks-table" class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <?php if ($isRecipientListView): ?>
            <tr>
              <th><?= __t('id') ?></th>
              <th><?= h(__t('workflow_task_task')) ?></th>
              <th><?= h(__t('workflow_task_due_date')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th class="text-end"><?= __t('actions') ?></th>
            </tr>
          <?php else: ?>
            <tr>
              <th><?= __t('id') ?></th>
              <th><?= h(__t('title')) ?></th>
              <th><?= h(__t('type')) ?></th>
              <th><?= h(__t('status')) ?></th>
              <th><?= h(__t('workflow_task_priority')) ?></th>
              <th><?= h(__t('workflow_task_assigned_to')) ?></th>
              <th><?= h(__t('workflow_task_created_by')) ?></th>
              <th><?= h(__t('workflow_task_due_date')) ?></th>
              <th><?= h(__t('workflow_task_completed')) ?></th>
              <th class="text-end"><?= __t('actions') ?></th>
            </tr>
          <?php endif; ?>
        </thead>
        <tbody>
        <?php if ($tasks !== []): ?>
          <?php foreach ($tasks as $t): ?>
            <?php
            $tid = (int) ($t['WorkflowTaskID'] ?? 0);
            $creatorId = (int) ($t['CreatedByUserID'] ?? 0);
            $assigneeId = (int) ($t['AssignedToUserID'] ?? 0);
            $rowCanManage = $canAdminWorkflow || ($canEditWorkflow && $creatorId === $currentUserId);
            $rowCanDelete = $canAdminWorkflow || ($creatorId > 0 && $creatorId === $currentUserId);
            $rowCanTransition = $canAdminWorkflow
                || (($canViewWorkflow || $canEditWorkflow) && ($assigneeId === $currentUserId || $creatorId === $currentUserId));
            $rowCanAccess = $rowCanManage || $rowCanTransition;
            $editQs = [
                'route' => 'workflow/edit',
                'id' => $tid,
                'q' => $q,
                'typeID' => $typeID,
                'statusID' => $statusID,
                'status' => $status,
                'due_state' => $dueState,
                'workflowProjectID' => $workflowProjectID,
                'page' => $page,
                'pageSize' => $pageSize,
                'mine' => $mine ? 1 : 0,
                'task_scope' => $mine && $taskScope !== 'all' ? $taskScope : null,
                'assignedToUserID' => $assignedToUserID,
            ];
            if ($isIframe) {
                $editQs['iframe'] = '1';
            }
            $editUrl = wf_build_query(array_filter($editQs, static fn($v) => $v !== null && $v !== ''));
            $isClosed = wf_is_closed_task($t);
            $isOverdue = wf_is_overdue($t);
            $rowDueState = wf_due_state($t);
            $recipientLastViewedAt = trim((string)($t['RecipientLastViewedAt'] ?? ''));
            $isUnreadForCurrentUser = !$isClosed
                && $assigneeId > 0
                && $assigneeId === $currentUserId
                && $recipientLastViewedAt === '';
            $rowClasses = array_filter([
                $isOverdue ? 'workflow-row-overdue' : '',
                $isUnreadForCurrentUser ? 'workflow-row-unread' : '',
            ]);
            ?>
            <tr class="<?= h(implode(' ', $rowClasses)) ?>">
              <?php if ($isRecipientListView): ?>
                <td><?= h((string) $tid) ?></td>
                <td>
                  <div class="workflow-task-title">
                    <?= h((string) ($t['Title'] ?? '')) ?>
                    <?php if ($isUnreadForCurrentUser): ?>
                      <span class="badge text-bg-warning ms-1"><?= h(__t('workflow_task_new')) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="workflow-recipient-task-meta">
                    <?php if (!empty($t['ProjectName'])): ?>
                      <span><i class="bi bi-kanban me-1"></i><?= h((string)$t['ProjectName']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($t['CreatedByName'])): ?>
                      <span><i class="bi bi-person me-1"></i><?= h(__t('workflow_task_from')) ?>: <?= h((string) $t['CreatedByName']) ?></span>
                    <?php endif; ?>
                    <?php $taskTypeLabel = (string) ($t['TaskTypeName'] ?? $typesMap[(int) ($t['TaskTypeID'] ?? 0)] ?? ''); ?>
                    <?php if ($taskTypeLabel !== ''): ?>
                      <span><?= h($taskTypeLabel) ?></span>
                    <?php endif; ?>
                    <span class="badge <?= h(wf_priority_badge_class($t['PriorityCode'] ?? null)) ?>">
                      <?= h(wf_priority_label($t['PriorityCode'] ?? null)) ?>
                    </span>
                  </div>
                  <?php if (!empty($t['RelatedEntity']) || !empty($t['RelatedKey'])): ?>
                    <div class="workflow-meta-line mt-1">
                      <?= h((string) ($t['RelatedEntity'] ?? '')) ?>
                      <?php if (!empty($t['RelatedKey'])): ?>
                        <span class="ms-1">#<?= h((string) $t['RelatedKey']) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <?php $formattedDueDate = wf_format_date($t['DueDate'] ?? null); ?>
                  <?php if ($formattedDueDate !== ''): ?>
                    <?= h($formattedDueDate) ?>
                  <?php else: ?>
                    <span class="text-muted"><?= h(__t('workflow_task_not_set')) ?></span>
                  <?php endif; ?>
                  <?php $dueLabel = wf_due_label($rowDueState); ?>
                  <?php if ($dueLabel !== ''): ?>
                    <div class="mt-1">
                      <span class="badge <?= h(wf_due_badge_class($rowDueState)) ?>"><?= h($dueLabel) ?></span>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= h(wf_status_badge_class($t)) ?>">
                    <?= h((string) ($t['StatusName'] ?? $statusesMap[(int) ($t['StatusID'] ?? 0)] ?? '')) ?>
                  </span>
                  <?php if ($isOverdue): ?>
                    <div class="workflow-meta-line mt-1 text-danger"><?= h(__t('workflow_task_overdue')) ?></div>
                  <?php endif; ?>
                </td>
              <?php else: ?>
                <td><?= h((string) $tid) ?></td>
                <td>
                  <div class="workflow-task-title">
                    <?= h((string) ($t['Title'] ?? '')) ?>
                    <?php if ($isUnreadForCurrentUser): ?>
                      <span class="badge text-bg-warning ms-1"><?= h(__t('workflow_task_new')) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($t['ProjectName'])): ?>
                    <div class="workflow-meta-line">
                      <i class="bi bi-kanban me-1"></i><?= h((string)$t['ProjectName']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($t['RelatedEntity']) || !empty($t['RelatedKey'])): ?>
                    <div class="workflow-meta-line">
                      <?= h((string) ($t['RelatedEntity'] ?? '')) ?>
                      <?php if (!empty($t['RelatedKey'])): ?>
                        <span class="ms-1">#<?= h((string) $t['RelatedKey']) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?= h((string) ($t['TaskTypeName'] ?? $typesMap[(int) ($t['TaskTypeID'] ?? 0)] ?? '')) ?></td>
                <td>
                  <span class="badge <?= h(wf_status_badge_class($t)) ?>">
                    <?= h((string) ($t['StatusName'] ?? $statusesMap[(int) ($t['StatusID'] ?? 0)] ?? '')) ?>
                  </span>
                  <?php if ($isOverdue): ?>
                    <div class="workflow-meta-line mt-1 text-danger"><?= h(__t('workflow_task_overdue')) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= h(wf_priority_badge_class($t['PriorityCode'] ?? null)) ?>">
                    <?= h(wf_priority_label($t['PriorityCode'] ?? null)) ?>
                  </span>
                </td>
                <td>
                  <?= h((string) ($t['AssignedToName'] ?? '')) ?>
                  <div class="workflow-meta-line">
                    <?php if ($recipientLastViewedAt !== ''): ?>
                      <i class="bi bi-eye me-1"></i><?= h(__t('workflow_task_viewed_at', ['time' => wf_format_datetime($recipientLastViewedAt)])) ?>
                    <?php else: ?>
                      <i class="bi bi-eye-slash me-1"></i><?= h(__t('workflow_task_not_viewed')) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= h((string) ($t['CreatedByName'] ?? '')) ?></td>
                <td>
                  <?= h(wf_format_date($t['DueDate'] ?? null)) ?>
                  <?php $dueLabel = wf_due_label($rowDueState); ?>
                  <?php if ($dueLabel !== ''): ?>
                    <div class="mt-1">
                      <span class="badge <?= h(wf_due_badge_class($rowDueState)) ?>"><?= h($dueLabel) ?></span>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?= h(wf_format_date($t['CompletedAt'] ?? null)) ?></td>
              <?php endif; ?>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <?php if ($rowCanAccess): ?>
                    <a href="<?= h($editUrl) ?>" class="btn btn-outline-secondary" title="<?= h($rowCanManage ? __t('edit') : __t('workflow_task_open_task')) ?>">
                      <i class="bi bi-<?= $rowCanManage ? 'pencil-square' : 'box-arrow-up-right' ?>"></i>
                    </a>
                  <?php endif; ?>
                  <?php if ($rowCanTransition): ?>
                    <form method="post"
                          action="index.php?route=workflow/transition"
                          class="d-inline workflow-transition-form"
                          <?php if (!$isClosed): ?>
                            data-confirm-title="<?= h(__t('workflow_task_mark_complete_title')) ?>"
                            data-confirm-message="<?= h(__t('workflow_task_mark_complete_confirm')) ?>"
                            data-confirm-button="<?= h(__t('workflow_task_mark_completed')) ?>"
                            data-confirm-button-class="btn-success"
                          <?php endif; ?>>
                      <?php if (function_exists('csrf_field')): ?>
                        <?= csrf_field(); ?>
                      <?php endif; ?>
                      <?= wf_render_context_inputs() ?>
                      <input type="hidden" name="WorkflowTaskID" value="<?= h((string) $tid) ?>">
                      <input type="hidden" name="q" value="<?= h($q) ?>">
                      <input type="hidden" name="page" value="<?= h((string) $page) ?>">
                      <input type="hidden" name="pageSize" value="<?= h((string) $pageSize) ?>">
                      <input type="hidden" name="mine" value="<?= $mine ? '1' : '0' ?>">
                      <?php if ($mine && $taskScope !== 'all'): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                      <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string) $typeID) ?>"><?php endif; ?>
                      <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string) $statusID) ?>"><?php endif; ?>
                      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                      <?php if ($dueState !== ''): ?><input type="hidden" name="due_state" value="<?= h($dueState) ?>"><?php endif; ?>
                      <?php if ($workflowProjectID !== null): ?><input type="hidden" name="workflowProjectID" value="<?= h((string) $workflowProjectID) ?>"><?php endif; ?>
                      <?php if ($assignedToUserID !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string) $assignedToUserID) ?>"><?php endif; ?>
                      <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                      <input type="hidden" name="transition" value="<?= $isClosed ? 'in_progress' : 'complete' ?>">
                      <button type="submit" class="btn btn-outline-<?= $isClosed ? 'warning' : 'success' ?>" title="<?= h($isClosed ? __t('workflow_task_mark_in_progress_title') : __t('workflow_task_mark_complete_title')) ?>">
                        <span class="spinner-border spinner-border-sm d-none workflow-transition-spinner" role="status" aria-hidden="true"></span>
                        <i class="bi bi-<?= $isClosed ? 'play-circle' : 'check2-circle' ?> workflow-transition-icon"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                  <?php if ($rowCanDelete && !$isIframe): ?>
                    <form method="post"
                          action="index.php?route=workflow/delete"
                          class="d-inline"
                          data-confirm-message="<?= h(__t('workflow_task_delete_confirm')) ?>"
                          data-confirm-button="<?= h(__t('delete')) ?>"
                          data-confirm-button-class="btn-danger">
                      <?php if (function_exists('csrf_field')): ?>
                        <?= csrf_field(); ?>
                      <?php endif; ?>
                      <?= wf_render_context_inputs() ?>
                      <input type="hidden" name="WorkflowTaskID" value="<?= h((string) $tid) ?>">
                      <input type="hidden" name="q" value="<?= h($q) ?>">
                      <input type="hidden" name="page" value="<?= h((string) $page) ?>">
                      <input type="hidden" name="pageSize" value="<?= h((string) $pageSize) ?>">
                      <input type="hidden" name="mine" value="<?= $mine ? '1' : '0' ?>">
                      <?php if ($mine && $taskScope !== 'all'): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                      <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string) $typeID) ?>"><?php endif; ?>
                      <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string) $statusID) ?>"><?php endif; ?>
                      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                      <?php if ($dueState !== ''): ?><input type="hidden" name="due_state" value="<?= h($dueState) ?>"><?php endif; ?>
                      <?php if ($workflowProjectID !== null): ?><input type="hidden" name="workflowProjectID" value="<?= h((string) $workflowProjectID) ?>"><?php endif; ?>
                      <?php if ($assignedToUserID !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string) $assignedToUserID) ?>"><?php endif; ?>
                      <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                      <button type="submit" class="btn btn-outline-danger" title="<?= __t('delete') ?>">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="<?= $isRecipientListView ? 5 : 10 ?>" class="text-center text-muted py-3"><?= h(__t('no_tasks_found')) ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <?php
      $pageBase = [
          'route' => 'workflow/list',
          'q' => $q,
          'typeID' => $typeID,
          'statusID' => $statusID,
          'status' => $status,
          'due_state' => $dueState,
          'workflowProjectID' => $workflowProjectID,
          'pageSize' => $pageSize,
          'mine' => $mine ? 1 : 0,
          'task_scope' => $mine && $taskScope !== 'all' ? $taskScope : null,
          'assignedToUserID' => $assignedToUserID,
      ];
      if ($isIframe) {
          $pageBase['iframe'] = '1';
      }
      $prevQs = $pageBase;
      $prevQs['page'] = max(1, $page - 1);
      $nextQs = $pageBase;
      $nextQs['page'] = min($totalPages, $page + 1);
      ?>
      <nav class="mt-3" aria-label="<?= h(__t('workflow_pagination')) ?>">
        <ul class="pagination justify-content-center pagination-sm">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(wf_build_query(array_filter($prevQs, static fn($v) => $v !== null && $v !== ''))) ?>">&laquo; <?= __t('prev') ?></a>
          </li>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $pi = $pageBase; $pi['page'] = $i; ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
              <a class="page-link" href="<?= h(wf_build_query(array_filter($pi, static fn($v) => $v !== null && $v !== ''))) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(wf_build_query(array_filter($nextQs, static fn($v) => $v !== null && $v !== ''))) ?>"><?= __t('next') ?> &raquo;</a>
          </li>
        </ul>
        <p class="text-center text-muted small">
          <?= h(__t('showing')) ?> <?= count($tasks) ?> <?= h(__t('of')) ?> <?= $total ?> <?= h(__t('entries')) ?>
        </p>
      </nav>
    <?php endif; ?>
</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.workflow-transition-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }

      form.dataset.submitting = '1';
      var button = event.submitter || form.querySelector('button[type="submit"]');
      if (button) {
        var spinner = button.querySelector('.workflow-transition-spinner');
        var icon = button.querySelector('.workflow-transition-icon');
        if (spinner) {
          spinner.classList.remove('d-none');
        }
        if (icon) {
          icon.classList.add('d-none');
        }
        button.setAttribute('aria-busy', 'true');
      }

      form.querySelectorAll('button[type="submit"]').forEach(function (submitButton) {
        submitButton.disabled = true;
      });
    });
  });
});
</script>

<?php if ($isIframe): ?>
</div>
</body>
</html>
<?php endif; ?>
