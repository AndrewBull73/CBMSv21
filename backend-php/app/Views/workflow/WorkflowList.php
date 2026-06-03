<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('t_or')) {
    function t_or(string $key, string $fallback): string {
        $t = __t($key);
        return $t === $key ? $fallback : $t;
    }
}

$title = $title ?? __t('workflow_tasks');
$tasks = is_array($tasks ?? null) ? $tasks : [];
$users = is_array($users ?? null) ? $users : [];
$total = (int) ($total ?? 0);
$totalPages = (int) ($totalPages ?? 0);
$page = max(1, (int) ($page ?? 1));
$pageSize = max(1, (int) ($pageSize ?? 25));
$q = (string) ($q ?? '');
$typeID = isset($typeID) && $typeID !== '' ? (int) $typeID : null;
$statusID = isset($statusID) && $statusID !== '' ? (int) $statusID : null;
$assignedToUserID = isset($assignedToUserID) && $assignedToUserID !== '' ? (int) $assignedToUserID : null;
$showAdminScope = !empty($showAdminScope);
$mine = !empty($mine);
$canEditWorkflow = !empty($canEditWorkflow);
$canAdminWorkflow = !empty($canAdminWorkflow);
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'open' => 0, 'overdue' => 0, 'closed' => 0];

$isIframe = !empty($_GET['iframe']);
$status = (string) ($_GET['status'] ?? '');

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
        $usersMap[$uid] = (string) ($u['DisplayName'] ?? $u['Username'] ?? ('User #' . $uid));
    }
}

function wf_format_date(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y', $ts) : '';
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

function wf_status_badge_class(array $task): string
{
    if (wf_is_closed_task($task)) {
        return 'text-bg-success';
    }
    if (wf_is_overdue($task)) {
        return 'text-bg-danger';
    }
    $statusCode = strtoupper(trim((string) ($task['StatusCode'] ?? '')));
    if ($statusCode === 'INPROGRESS') {
        return 'text-bg-warning';
    }
    return 'text-bg-primary';
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
  .workflow-shell .workflow-row-overdue {
    background: #fff7f7;
  }
  .workflow-shell .workflow-task-title {
    font-weight: 600;
    color: #24384d;
  }
  .workflow-shell .workflow-meta-line {
    color: #6f7f90;
    font-size: .78rem;
  }
</style>

<div class="workflow-shell">
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center">
      <i class="bi bi-clipboard-check me-2"></i>
      <strong><?= h($title) ?></strong>
      <?php if ($status === 'open'): ?>
        <small class="text-muted ms-2"><?= t_or('open_only', 'Open only') ?></small>
      <?php endif; ?>
    </div>
    <?php if ($canEditWorkflow): ?>
      <?php
      $createQs = ['route' => 'workflow/edit', 'mine' => $mine ? 1 : 0];
      if ($assignedToUserID !== null) {
          $createQs['assignedToUserID'] = $assignedToUserID;
      }
      if ($isIframe) {
          $createQs['iframe'] = '1';
      }
      $createUrl = wf_build_query($createQs);
      ?>
      <a href="<?= h($createUrl) ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i><?= t_or('create_task', 'Create Task') ?>
      </a>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php if (!empty($flash) && is_array($flash) && !empty($flash['text'])): ?>
      <div class="alert alert-<?= h($flash['type'] ?? 'info') ?> alert-dismissible fade show mb-3" role="alert">
        <?= $flash['text'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __t('close') ?>"></button>
      </div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
      <div class="col-6 col-lg-3">
        <div class="workflow-metric">
          <div class="workflow-metric-label">Open Tasks</div>
          <div class="workflow-metric-value"><?= (int) ($summary['open'] ?? 0) ?></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="workflow-metric">
          <div class="workflow-metric-label">Overdue</div>
          <div class="workflow-metric-value text-danger"><?= (int) ($summary['overdue'] ?? 0) ?></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="workflow-metric">
          <div class="workflow-metric-label">Closed</div>
          <div class="workflow-metric-value text-success"><?= (int) ($summary['closed'] ?? 0) ?></div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="workflow-metric">
          <div class="workflow-metric-label">Total</div>
          <div class="workflow-metric-value"><?= (int) ($summary['total'] ?? 0) ?></div>
        </div>
      </div>
    </div>

    <?php if ($showAdminScope && !$isIframe): ?>
      <div class="workflow-scope-strip d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <div class="fw-semibold small">Task Scope</div>
          <div class="text-muted small">Switch between your own tasks and the full workflow register.</div>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="Task scope">
          <?php
          $scopeBase = ['route' => 'workflow/list', 'q' => $q, 'typeID' => $typeID, 'statusID' => $statusID, 'pageSize' => $pageSize];
          ?>
          <a class="btn <?= $mine ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= h(wf_build_query(array_filter($scopeBase + ['mine' => 1], static fn($v) => $v !== null && $v !== ''))) ?>">
            My Tasks
          </a>
          <a class="btn <?= !$mine ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= h(wf_build_query(array_filter($scopeBase + ['mine' => 0], static fn($v) => $v !== null && $v !== ''))) ?>">
            All Tasks
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php
    $filterBase = ['route' => 'workflow/list', 'page' => 1];
    if ($status !== '') {
        $filterBase['status'] = $status;
    }
    if ($showAdminScope) {
        $filterBase['mine'] = $mine ? 1 : 0;
    }
    if ($isIframe) {
        $filterBase['iframe'] = '1';
    }
    ?>
    <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
      <?php foreach ($filterBase as $k => $v): ?>
        <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
      <?php endforeach; ?>
      <?= wf_render_context_inputs() ?>

      <div class="col-md-3">
        <label for="q" class="form-label"><?= __t('search') ?></label>
        <input type="text" id="q" name="q" class="form-control" value="<?= h($q) ?>" placeholder="<?= t_or('search_placeholder', 'Search tasks...') ?>">
      </div>

      <div class="col-md-2">
        <label for="typeID" class="form-label"><?= t_or('type', 'Type') ?></label>
        <select id="typeID" name="typeID" class="form-select">
          <option value=""><?= t_or('all_types', 'All Types') ?></option>
          <?php foreach ($typesMap as $id => $name): ?>
            <option value="<?= h((string) $id) ?>" <?= $typeID === $id ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label for="statusID" class="form-label"><?= t_or('status', 'Status') ?></label>
        <select id="statusID" name="statusID" class="form-select">
          <option value=""><?= t_or('all_statuses', 'All Statuses') ?></option>
          <?php foreach ($statusesMap as $id => $name): ?>
            <option value="<?= h((string) $id) ?>" <?= $statusID === $id ? 'selected' : '' ?>><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($showAdminScope && !$mine && !$isIframe): ?>
        <div class="col-md-3">
          <label for="assignedToUserID" class="form-label">Assigned To</label>
          <select id="assignedToUserID" name="assignedToUserID" class="form-select">
            <option value="">All Users</option>
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
            <option value="<?= $ps ?>" <?= $pageSize === $ps ? 'selected' : '' ?>><?= $ps ?>/page</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-search me-1"></i><?= __t('filter') ?>
        </button>
        <a href="<?= h(wf_build_query(['route' => 'workflow/list'] + ($isIframe ? ['iframe' => 1] : []))) ?>" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-arrow-repeat me-1"></i><?= __t('reset') ?>
        </a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= __t('id') ?></th>
            <th><?= t_or('title', 'Title') ?></th>
            <th><?= t_or('type', 'Type') ?></th>
            <th><?= t_or('status', 'Status') ?></th>
            <th><?= t_or('assigned_to', 'Assigned To') ?></th>
            <th><?= t_or('due_date', 'Due Date') ?></th>
            <th><?= t_or('completed_at', 'Completed') ?></th>
            <th class="text-end"><?= __t('actions') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if ($tasks !== []): ?>
          <?php foreach ($tasks as $t): ?>
            <?php
            $tid = (int) ($t['WorkflowTaskID'] ?? 0);
            $editQs = [
                'route' => 'workflow/edit',
                'id' => $tid,
                'q' => $q,
                'typeID' => $typeID,
                'statusID' => $statusID,
                'status' => $status,
                'page' => $page,
                'pageSize' => $pageSize,
                'mine' => $mine ? 1 : 0,
                'assignedToUserID' => $assignedToUserID,
            ];
            if ($isIframe) {
                $editQs['iframe'] = '1';
            }
            $editUrl = wf_build_query(array_filter($editQs, static fn($v) => $v !== null && $v !== ''));
            $isClosed = wf_is_closed_task($t);
            $isOverdue = wf_is_overdue($t);
            ?>
            <tr class="<?= $isOverdue ? 'workflow-row-overdue' : '' ?>">
              <td><?= h((string) $tid) ?></td>
              <td>
                <div class="workflow-task-title"><?= h((string) ($t['Title'] ?? '')) ?></div>
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
                  <div class="workflow-meta-line mt-1 text-danger">Overdue</div>
                <?php endif; ?>
              </td>
              <td><?= h((string) ($t['AssignedToName'] ?? '')) ?></td>
              <td><?= h(wf_format_date($t['DueDate'] ?? null)) ?></td>
              <td><?= h(wf_format_date($t['CompletedAt'] ?? null)) ?></td>
              <td class="text-end">
                <div class="btn-group btn-group-sm" role="group">
                  <?php if ($canEditWorkflow): ?>
                    <a href="<?= h($editUrl) ?>" class="btn btn-outline-secondary" title="<?= __t('edit') ?>">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <form method="post" action="index.php?route=workflow/transition" class="d-inline">
                      <?php if (function_exists('csrf_field')): ?>
                        <?= csrf_field(); ?>
                      <?php endif; ?>
                      <?= wf_render_context_inputs() ?>
                      <input type="hidden" name="WorkflowTaskID" value="<?= h((string) $tid) ?>">
                      <input type="hidden" name="q" value="<?= h($q) ?>">
                      <input type="hidden" name="page" value="<?= h((string) $page) ?>">
                      <input type="hidden" name="pageSize" value="<?= h((string) $pageSize) ?>">
                      <input type="hidden" name="mine" value="<?= $mine ? '1' : '0' ?>">
                      <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string) $typeID) ?>"><?php endif; ?>
                      <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string) $statusID) ?>"><?php endif; ?>
                      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                      <?php if ($assignedToUserID !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string) $assignedToUserID) ?>"><?php endif; ?>
                      <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                      <input type="hidden" name="transition" value="<?= $isClosed ? 'reopen' : 'complete' ?>">
                      <button type="submit" class="btn btn-outline-<?= $isClosed ? 'warning' : 'success' ?>" title="<?= $isClosed ? 'Reopen task' : 'Mark complete' ?>">
                        <i class="bi bi-<?= $isClosed ? 'arrow-counterclockwise' : 'check2-circle' ?>"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canAdminWorkflow && !$isIframe): ?>
                    <form method="post" action="index.php?route=workflow/delete" class="d-inline" onsubmit="return confirm('Delete this task?');">
                      <?php if (function_exists('csrf_field')): ?>
                        <?= csrf_field(); ?>
                      <?php endif; ?>
                      <?= wf_render_context_inputs() ?>
                      <input type="hidden" name="WorkflowTaskID" value="<?= h((string) $tid) ?>">
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
          <tr><td colspan="8" class="text-center text-muted py-3"><?= t_or('no_tasks_found', 'No tasks found.') ?></td></tr>
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
          'pageSize' => $pageSize,
          'mine' => $mine ? 1 : 0,
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
      <nav class="mt-3" aria-label="Workflow pagination">
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
          <?= __t('showing') ?> <?= count($tasks) ?> <?= __t('of') ?> <?= $total ?> <?= t_or('entries', 'entries') ?>
        </p>
      </nav>
    <?php endif; ?>
  </div>
</div>
</div>

<?php if ($isIframe): ?>
</div>
</body>
</html>
<?php endif; ?>
